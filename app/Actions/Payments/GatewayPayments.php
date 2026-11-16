<?php

namespace App\Actions\Payments;

use App\Enums\GatewayTransactionStatus;
use App\Enums\PaymentStatus;
use App\Models\GatewayTransaction;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\PaymentSubmitted;
use App\Support\Gateways\GatewayCheckout;
use App\Support\Gateways\GatewayException;
use App\Support\Gateways\GatewayManager;
use App\Support\Gateways\PaymentGateway;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Online payments (REQ §12.3, §12.5 rules 4, 5 and 9).
 *
 *  - start():  open a session with the gateway and hand back its checkout URL.
 *  - settle(): called for every callback (server-to-server IPN and the
 *              payer's browser return alike). The gateway is asked directly
 *              what happened; the callback's own claims are never trusted.
 *              A transaction can only become a payment once, however many
 *              times its callbacks arrive.
 */
class GatewayPayments
{
    public function __construct(private GatewayManager $gateways, private PaymentLedger $ledger) {}

    /** Open a gateway session and say where the payer should go. */
    public function start(PaymentRequest $request, User $actor): GatewayCheckout
    {
        $gateway = $this->gateways->active();

        if ($gateway === null) {
            throw ValidationException::withMessages(['gateway' => __('Online payment is not available right now. Please use another method.')]);
        }

        $transaction = DB::transaction(function () use ($request, $actor, $gateway) {
            $request = PaymentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->status->isOpen()) {
                throw ValidationException::withMessages(['gateway' => __('This invoice is :status and no longer accepts payments.', ['status' => strtolower($request->status->label())])]);
            }

            $awaiting = (int) $request->payments()->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Verified->value])->sum('amount');
            $amount = $request->outstanding() - $awaiting;

            if ($amount <= 0) {
                throw ValidationException::withMessages(['gateway' => __('Nothing is left to pay on this invoice.')]);
            }

            return GatewayTransaction::create([
                'gateway' => $gateway->key(),
                // SSLCommerz allows 30 characters; a ULID is 26.
                'tran_id' => 'RTL'.Str::ulid()->toBase32(),
                'payment_request_id' => $request->id,
                'student_id' => $request->student_id,
                'amount' => $amount,
                'currency' => $request->currency,
                'status' => GatewayTransactionStatus::Initiated,
                'initiated_by' => $actor->id,
            ]);
        });

        try {
            $checkout = $gateway->initiate($transaction, [
                // "t" names the attempt in case the gateway returns the payer
                // without posting its usual fields. It grants nothing: every
                // return is checked with the gateway.
                'success' => route('gateway.return', ['gateway' => $gateway->key(), 'outcome' => 'success', 't' => $transaction->tran_id]),
                'fail' => route('gateway.return', ['gateway' => $gateway->key(), 'outcome' => 'fail', 't' => $transaction->tran_id]),
                'cancel' => route('gateway.return', ['gateway' => $gateway->key(), 'outcome' => 'cancel', 't' => $transaction->tran_id]),
                'ipn' => route('gateway.ipn', ['gateway' => $gateway->key()]),
            ]);
        } catch (GatewayException $e) {
            Log::warning('Payment gateway session failed', ['gateway' => $gateway->key(), 'tran_id' => $transaction->tran_id, 'error' => $e->getMessage()]);
            $transaction->update(['status' => GatewayTransactionStatus::Failed, 'failure_reason' => mb_substr($e->getMessage(), 0, 250)]);

            throw ValidationException::withMessages(['gateway' => __('The payment gateway could not be reached. Please try again, or use another method.')]);
        }

        $transaction->update(['session_key' => $checkout->sessionKey]);

        return $checkout;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  'ipn'|'return'  $source
     */
    public function settle(string $gatewayKey, array $payload, string $source): ?GatewayTransaction
    {
        $gateway = $this->gateways->driver($gatewayKey);
        $tranId = $gateway?->transactionId($payload);

        if ($gateway === null || $tranId === null) {
            return null;
        }

        $payload = self::scrub($payload);
        $event = $this->recordEvent($gateway, $tranId, $payload, $source);
        $transaction = GatewayTransaction::where('gateway', $gateway->key())->where('tran_id', $tranId)->first();

        if ($transaction === null) {
            $event->update(['result' => 'unknown_transaction', 'processed_at' => now()]);

            return null;
        }

        // Already turned into a payment: a repeat delivery changes nothing.
        if ($transaction->status->isSettled()) {
            $event->update(['result' => 'duplicate', 'processed_at' => now()]);

            return $transaction;
        }

        // Ask the gateway before taking any lock: this is a network call.
        $verification = $gateway->verify($transaction, $payload);

        if ($verification->status === GatewayTransactionStatus::Initiated) {
            $event->update(['result' => 'unverified']);
            Log::warning('Payment gateway validation unavailable', ['tran_id' => $tranId, 'reason' => $verification->reason]);

            return $transaction;
        }

        $payment = null;

        $transaction = DB::transaction(function () use ($transaction, $verification, $gateway, $payload, &$payment) {
            $request = PaymentRequest::whereKey($transaction->payment_request_id)->lockForUpdate()->firstOrFail();
            $transaction = GatewayTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            // Lost the race to another delivery of the same callback.
            if ($transaction->status->isSettled()) {
                return $transaction;
            }

            $transaction->fill([
                'status' => $verification->status,
                'validation_id' => $verification->validationId ?? $transaction->validation_id,
                'bank_tran_id' => $verification->bankTransactionId ?? $transaction->bank_tran_id,
                'card_type' => $verification->cardType ?? $transaction->card_type,
                'failure_reason' => $verification->reason !== null ? mb_substr($verification->reason, 0, 250) : null,
                'payload' => ['callback' => $payload, 'validation' => $verification->raw],
            ]);

            if ($verification->status->isSettled()) {
                $hold = $verification->status === GatewayTransactionStatus::Review;
                $method = PaymentMethod::where('slug', $this->gateways->methodSlug($gateway->key()))->firstOrFail();
                $overpaid = $transaction->amount > $request->outstanding();

                $payment = $this->ledger->recordGatewayPayment(
                    $transaction,
                    $request,
                    $method,
                    $hold,
                    $verification->bankTransactionId ?? $transaction->tran_id,
                    trim(($hold
                        ? __('Paid online through :gateway, held for review: :reason', ['gateway' => $gateway->label(), 'reason' => $verification->reason])
                        : __('Paid online and validated with :gateway (:tran).', ['gateway' => $gateway->label(), 'tran' => $transaction->tran_id]))
                        .($overpaid ? ' '.__('More than the outstanding balance: refund the excess.') : '')),
                );

                $transaction->payment_id = $payment->id;
                $transaction->settled_at = now();
                $transaction->save();
            } else {
                $transaction->save();
                $this->ledger->recalculate($request);
            }

            return $transaction;
        });

        $event->update(['result' => $transaction->status->value, 'processed_at' => now()]);

        if ($payment instanceof Payment) {
            if ($payment->status === PaymentStatus::Confirmed) {
                $this->ledger->announce($payment);
            } else {
                Notification::send(PaymentLedger::financeStaff(), new PaymentSubmitted($payment->load('student.user', 'method')));
            }
        }

        return $transaction;
    }

    /**
     * REQ §12.5 rule 9: one row per distinct callback; a repeat only bumps
     * the delivery count.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(PaymentGateway $gateway, string $tranId, array $payload, string $source): WebhookEvent
    {
        $key = hash('sha256', implode('|', [$gateway->key(), $tranId, (string) ($payload['val_id'] ?? ''), strtoupper((string) ($payload['status'] ?? ''))]));

        try {
            return WebhookEvent::create(['gateway' => $gateway->key(), 'event_key' => $key, 'tran_id' => $tranId, 'source' => $source, 'payload' => $payload]);
        } catch (UniqueConstraintViolationException) {
            $event = WebhookEvent::where('event_key', $key)->firstOrFail();
            $event->increment('deliveries');

            return $event;
        }
    }

    /**
     * Keep only short scalar fields, and never anything that looks like a
     * credential or a full card number.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private static function scrub(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            if (! is_scalar($value) || preg_match('/pass|secret|token|cvv|cvc/i', $key)) {
                continue;
            }

            $clean[mb_substr((string) $key, 0, 64)] = mb_substr((string) $value, 0, 500);

            if (count($clean) >= 60) {
                break;
            }
        }

        return $clean;
    }
}
