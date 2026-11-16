<?php

namespace App\Actions\Payments;

use App\Enums\GatewayTransactionStatus;
use App\Enums\PaymentMethodKind;
use App\Enums\PaymentRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\UserStatus;
use App\Models\GatewayTransaction;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\PaymentStatusHistory;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentFile;
use App\Models\User;
use App\Notifications\PaymentDecided;
use App\Notifications\PaymentRequested;
use App\Notifications\PaymentSubmitted;
use App\Support\Numbering\NumberGenerator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every money state change goes through here (REQ §12.1, §12.5):
 *
 *  - each change runs in a database transaction with the rows locked;
 *  - a request's status is derived from its payments (recalculate), never
 *    typed in by hand;
 *  - every status change is written to payment_status_histories;
 *  - the person who raised a request can never confirm payments on it.
 */
class PaymentLedger
{
    public function __construct(private NumberGenerator $numbers) {}

    /** REQ §12.1 steps 1–3. */
    public function createRequest(Student $student, PaymentType $type, int $amount, ?CarbonInterface $dueDate, ?string $description, ?StudentFile $file, ?User $actor, ?string $notes = null): PaymentRequest
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('The amount must be greater than zero.')]);
        }

        if ($file !== null && $file->student_id !== $student->id) {
            throw ValidationException::withMessages(['student_file_id' => __('That file does not belong to this student.')]);
        }

        $request = DB::transaction(function () use ($student, $type, $amount, $dueDate, $description, $file, $actor, $notes) {
            $request = PaymentRequest::create([
                'invoice_number' => $this->numbers->next('invoice'),
                'student_id' => $student->id,
                'student_file_id' => $file?->id,
                'payment_type_id' => $type->id,
                'description' => $description,
                'amount' => $amount,
                'currency' => config('payments.currency', 'BDT'),
                'due_date' => $dueDate,
                'requested_by' => $actor?->id,
                'notes' => $notes,
                'status' => PaymentRequestStatus::Unpaid,
            ]);

            $this->history($request, null, PaymentRequestStatus::Unpaid->value, $actor, __('Payment request raised'));
            $this->recalculate($request, $actor);

            return $request;
        });

        $student->user->notify(new PaymentRequested($request->load('type')));

        return $request;
    }

    /** Withdraw a request raised by mistake. Only possible before any money moved. */
    public function cancelRequest(PaymentRequest $request, User $actor, string $reason): void
    {
        DB::transaction(function () use ($request, $actor, $reason) {
            $request = PaymentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->amount_paid > 0 || $request->payments()->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Verified->value])->exists()) {
                throw ValidationException::withMessages(['request' => __('This request has payments against it and cannot be cancelled.')]);
            }

            if ($request->status === PaymentRequestStatus::Cancelled) {
                return;
            }

            $from = $request->status->value;
            $request->update(['status' => PaymentRequestStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancel_reason' => $reason]);
            $this->history($request, $from, PaymentRequestStatus::Cancelled->value, $actor, $reason);
        });
    }

    /**
     * REQ §12.1 step 4: a payment is submitted (by the student, or by staff
     * on their behalf) and waits for Accounts.
     */
    public function submitPayment(PaymentRequest $request, PaymentMethod $method, int $amount, ?string $reference, CarbonInterface $paidOn, ?UploadedFile $evidence, User $actor, ?string $notes = null, bool $notifyFinance = true, bool $evidenceOptional = false): Payment
    {
        $stored = $evidence ? $this->storeEvidence($request, $evidence) : null;

        try {
            $payment = DB::transaction(function () use ($request, $method, $amount, $reference, $paidOn, $stored, $actor, $notes, $evidenceOptional) {
                $request = PaymentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

                $this->guardSubmission($request, $method, $amount, $reference, $stored !== null || $evidenceOptional);

                $payment = $request->payments()->create([
                    'student_id' => $request->student_id,
                    'payment_method_id' => $method->id,
                    'amount' => $amount,
                    'currency' => $request->currency,
                    'transaction_reference' => $reference !== null ? trim($reference) : null,
                    'paid_on' => $paidOn,
                    'evidence_disk' => $stored['disk'] ?? null,
                    'evidence_path' => $stored['path'] ?? null,
                    'evidence_original_name' => $stored['name'] ?? null,
                    'evidence_mime_type' => $stored['mime'] ?? null,
                    'status' => PaymentStatus::Pending,
                    'submitted_by' => $actor->id,
                    'notes' => $notes,
                ]);

                $this->history($payment, null, PaymentStatus::Pending->value, $actor, __('Payment submitted'));
                $this->recalculate($request, $actor);

                return $payment;
            });
        } catch (\Throwable $e) {
            if ($stored) {
                Storage::disk($stored['disk'])->delete($stored['path']);
            }

            throw $e;
        }

        if ($notifyFinance) {
            Notification::send(self::financeStaff(), new PaymentSubmitted($payment->load('student.user', 'method')));
        }

        return $payment;
    }

    /** Optional first step: evidence checked, final confirmation still to come. */
    public function verify(Payment $payment, User $actor): void
    {
        DB::transaction(function () use ($payment, $actor) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== PaymentStatus::Pending) {
                throw ValidationException::withMessages(['payment' => __('Only payments awaiting verification can be verified.')]);
            }

            $payment->update(['status' => PaymentStatus::Verified, 'verified_by' => $actor->id, 'verified_at' => now()]);
            $this->history($payment, PaymentStatus::Pending->value, PaymentStatus::Verified->value, $actor, null);
        });
    }

    /**
     * REQ §12.1 steps 6–9. Idempotent and race-safe: rows are locked and a
     * payment that is no longer awaiting a decision is refused, so a double
     * click or a retried request cannot count the money twice (rule 9).
     */
    public function confirm(Payment $payment, User $actor, ?string $note = null): Payment
    {
        $payment = DB::transaction(function () use ($payment, $actor, $note) {
            $request = PaymentRequest::whereKey($payment->payment_request_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            self::guardConfirmer($request, $actor);

            if (! $payment->status->isAwaitingDecision()) {
                throw ValidationException::withMessages(['payment' => __('This payment has already been :status.', ['status' => strtolower($payment->status->label())])]);
            }

            if ($payment->amount > $request->outstanding()) {
                throw ValidationException::withMessages(['payment' => __('This payment is more than the outstanding balance of the request.')]);
            }

            $from = $payment->status->value;
            $payment->update([
                'status' => PaymentStatus::Confirmed,
                'verified_by' => $payment->verified_by ?? $actor->id,
                'verified_at' => $payment->verified_at ?? now(),
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'receipt_number' => $this->numbers->next('receipt'),
            ]);
            $this->history($payment, $from, PaymentStatus::Confirmed->value, $actor, $note);

            $request->amount_paid += $payment->amount;
            $request->save();
            $this->recalculate($request, $actor);

            return $payment;
        });

        $this->notifyDecision($payment);

        return $payment;
    }

    public function reject(Payment $payment, User $actor, string $reason): Payment
    {
        $payment = DB::transaction(function () use ($payment, $actor, $reason) {
            $request = PaymentRequest::whereKey($payment->payment_request_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            self::guardConfirmer($request, $actor);

            if (! $payment->status->isAwaitingDecision()) {
                throw ValidationException::withMessages(['payment' => __('This payment has already been :status.', ['status' => strtolower($payment->status->label())])]);
            }

            $from = $payment->status->value;
            $payment->update(['status' => PaymentStatus::Rejected, 'rejection_reason' => $reason, 'confirmed_by' => null, 'confirmed_at' => null]);
            $this->history($payment, $from, PaymentStatus::Rejected->value, $actor, $reason);
            $this->recalculate($request, $actor);

            return $payment;
        });

        $this->notifyDecision($payment);

        return $payment;
    }

    /**
     * Accounts recording money received in person (REQ §12.3 "Cash Payment",
     * "Manual Payment Entry"): submitted and confirmed in one step. Accounts
     * is vouching for the money itself, so no proof upload is asked for,
     * whatever the method normally requires of a student.
     */
    public function recordReceived(PaymentRequest $request, PaymentMethod $method, int $amount, ?string $reference, CarbonInterface $paidOn, User $actor, ?string $notes): Payment
    {
        self::guardConfirmer($request, $actor);

        return DB::transaction(function () use ($request, $method, $amount, $reference, $paidOn, $actor, $notes) {
            $payment = $this->submitPayment($request, $method, $amount, $reference, $paidOn, null, $actor, $notes, notifyFinance: false, evidenceOptional: true);

            return $this->confirm($payment, $actor, __('Recorded as received by Accounts'));
        });
    }

    /**
     * REQ §12.3 online gateway: money the gateway has confirmed server-side.
     * There is no person to confirm it, so the payment is confirmed by the
     * system, unless the gateway flagged it as risky ($hold), in which case
     * it waits for Accounts like any submitted payment.
     *
     * Call inside a transaction that already holds the request row lock.
     * The amount is recorded even if it exceeds the outstanding balance: the
     * student has really been charged, and Accounts can refund the excess.
     */
    public function recordGatewayPayment(GatewayTransaction $transaction, PaymentRequest $request, PaymentMethod $method, bool $hold, ?string $reference, string $note): Payment
    {
        $payment = $request->payments()->create([
            'student_id' => $request->student_id,
            'payment_method_id' => $method->id,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'transaction_reference' => $reference,
            'paid_on' => today(),
            'status' => $hold ? PaymentStatus::Pending : PaymentStatus::Confirmed,
            'submitted_by' => $transaction->initiated_by,
            'verified_at' => $hold ? null : now(),
            'confirmed_at' => $hold ? null : now(),
            'receipt_number' => $hold ? null : $this->numbers->next('receipt'),
            'notes' => $note,
        ]);

        $this->history($payment, null, $payment->status->value, null, $note);

        if (! $hold) {
            $request->amount_paid += $payment->amount;
            $request->save();
        }

        $this->recalculate($request);

        return $payment;
    }

    /** Tell the student and counselor about a payment the system confirmed. */
    public function announce(Payment $payment): void
    {
        $this->notifyDecision($payment);
    }

    /**
     * Derive the request status from its money (REQ §12.4): payments,
     * refunds and online attempts.
     */
    public function recalculate(PaymentRequest $request, ?User $actor = null): void
    {
        if ($request->status === PaymentRequestStatus::Cancelled) {
            return;
        }

        $awaiting = $request->payments()->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Verified->value])->exists();
        $lastDecided = $request->payments()->whereIn('status', [PaymentStatus::Confirmed->value, PaymentStatus::Rejected->value])->reorder()->latest('updated_at')->latest('id')->first();
        $overdue = $request->due_date !== null && $request->due_date->isBefore(today());
        $refundOpen = $request->refunds()->whereIn('status', [RefundStatus::Requested->value, RefundStatus::Approved->value])->exists();
        $lastAttempt = $request->gatewayTransactions()->reorder()->latest('id')->first();
        // The most recent thing that happened was an online attempt that failed.
        $gatewayFailed = $lastAttempt !== null
            && in_array($lastAttempt->status, [GatewayTransactionStatus::Failed, GatewayTransactionStatus::Invalid], true)
            && ($lastDecided === null || $lastDecided->updated_at?->isBefore($lastAttempt->updated_at));

        // Compare enums, not strings: Eloquent casts the status columns.
        $status = match (true) {
            $request->amount_paid > 0 && $request->amount_refunded >= $request->amount_paid => PaymentRequestStatus::Refunded,
            // A refund in progress only relabels an invoice that is otherwise
            // settled. One with a balance stays partial or due, so it remains
            // on the due lists and can still be paid.
            $request->amount_paid >= $request->amount && $refundOpen => PaymentRequestStatus::RefundRequested,
            $request->amount_paid >= $request->amount => PaymentRequestStatus::Paid,
            $awaiting => PaymentRequestStatus::Pending,
            $overdue => PaymentRequestStatus::Due,
            $request->amount_paid > 0 => PaymentRequestStatus::Partial,
            $gatewayFailed => PaymentRequestStatus::Failed,
            $lastDecided?->status === PaymentStatus::Rejected => PaymentRequestStatus::Rejected,
            default => PaymentRequestStatus::Unpaid,
        };

        if ($status === $request->status) {
            return;
        }

        $from = $request->status->value;
        $request->status = $status;

        if ($status === PaymentRequestStatus::Paid) {
            $request->paid_at ??= now();
        } elseif ($request->amount_paid < $request->amount) {
            $request->paid_at = null;
        }

        $request->save();

        $this->history($request, $from, $status->value, $actor, null);
    }

    /**
     * REQ §27 rule 6: counselors cannot confirm their own payment requests.
     * Applied to everyone, Super Admin included, as a separation-of-duties rule.
     */
    public static function guardConfirmer(PaymentRequest $request, User $actor): void
    {
        if ($request->requested_by !== null && $request->requested_by === $actor->id) {
            throw ValidationException::withMessages(['payment' => __('You raised this payment request, so someone else must confirm its payments.')]);
        }
    }

    /** @return Collection<int, User> */
    public static function financeStaff(): Collection
    {
        return User::permission('payments.verify')->where('status', UserStatus::Active->value)->get();
    }

    private function guardSubmission(PaymentRequest $request, PaymentMethod $method, int $amount, ?string $reference, bool $hasEvidence): void
    {
        if (! $request->status->isOpen()) {
            throw ValidationException::withMessages(['request' => __('This request is :status and no longer accepts payments.', ['status' => strtolower($request->status->label())])]);
        }

        if (! $method->is_active || $method->kind === PaymentMethodKind::Gateway) {
            throw ValidationException::withMessages(['payment_method_id' => __('That payment method is not available.')]);
        }

        $awaiting = (int) $request->payments()->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Verified->value])->sum('amount');

        if ($amount <= 0 || $amount > $request->outstanding() - $awaiting) {
            throw ValidationException::withMessages(['amount' => __('The amount must be between 0.01 and the outstanding balance not already awaiting verification.')]);
        }

        if ($method->requires_reference && blank($reference)) {
            throw ValidationException::withMessages(['transaction_reference' => __('Enter the transaction ID or reference for this payment.')]);
        }

        if ($method->requires_evidence && ! $hasEvidence) {
            throw ValidationException::withMessages(['evidence' => __('Upload proof of payment (deposit slip or screenshot).')]);
        }

        // The same bank or mobile-banking reference can't pay twice.
        if (filled($reference) && Payment::where('payment_method_id', $method->id)
            ->where('transaction_reference', trim((string) $reference))
            ->where('status', '!=', PaymentStatus::Rejected->value)
            ->exists()) {
            throw ValidationException::withMessages(['transaction_reference' => __('A payment with this transaction reference has already been submitted.')]);
        }
    }

    /** @return array{disk: string, path: string, name: string, mime: string} */
    private function storeEvidence(PaymentRequest $request, UploadedFile $file): array
    {
        $disk = (string) config('documents.disk');
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $path = $file->storeAs('payment-evidence/'.$request->student_id, Str::uuid()->toString().'.'.$extension, $disk);

        if ($path === false) {
            throw ValidationException::withMessages(['evidence' => __('The file could not be saved. Please try again.')]);
        }

        return ['disk' => $disk, 'path' => $path, 'name' => mb_substr($file->getClientOriginalName(), 0, 255), 'mime' => (string) $file->getMimeType()];
    }

    /** REQ §12.5 rule 7: one row per status change, for requests, payments and refunds. */
    public function history(Model $subject, ?string $from, string $to, ?User $actor, ?string $note): void
    {
        $subject->morphMany(PaymentStatusHistory::class, 'subject')->create([
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actor?->id,
            'note' => $note,
        ]);
    }

    private function notifyDecision(Payment $payment): void
    {
        $payment->load(['request', 'student.user', 'student.counselor']);
        $recipients = collect([$payment->student->user, $payment->student->counselor])->filter()->unique('id');

        Notification::send($recipients, new PaymentDecided($payment));
    }
}
