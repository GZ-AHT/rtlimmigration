<?php

namespace App\Actions\Payments;

use App\Enums\PaymentMethodKind;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\RefundUpdated;
use App\Support\Numbering\NumberGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Refunds (REQ §9.1, §12.5 rule 8, §27 rule 10): requested against one
 * confirmed payment, approved by someone authorised who did not request it,
 * then paid out by Accounts. Every step is a locked transaction with a
 * history row, like the rest of the ledger.
 */
class RefundManager
{
    public function __construct(private PaymentLedger $ledger, private NumberGenerator $numbers) {}

    public function request(Payment $payment, int $amount, string $reason, User $actor): Refund
    {
        $refund = DB::transaction(function () use ($payment, $amount, $reason, $actor) {
            $request = PaymentRequest::whereKey($payment->payment_request_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== PaymentStatus::Confirmed) {
                throw ValidationException::withMessages(['amount' => __('Only confirmed payments can be refunded.')]);
            }

            if ($amount <= 0 || $amount > $payment->refundable()) {
                throw ValidationException::withMessages(['amount' => __('The amount must be between 0.01 and what is still refundable on this payment.')]);
            }

            $refund = Refund::create([
                'refund_number' => $this->numbers->next('refund'),
                'payment_id' => $payment->id,
                'payment_request_id' => $request->id,
                'student_id' => $payment->student_id,
                'amount' => $amount,
                'currency' => $payment->currency,
                'reason' => $reason,
                'status' => RefundStatus::Requested,
                'requested_by' => $actor->id,
            ]);

            $this->ledger->history($refund, null, RefundStatus::Requested->value, $actor, $reason);
            $this->ledger->recalculate($request, $actor);

            return $refund;
        });

        Notification::send(self::approvers()->reject(fn (User $u) => $u->is($actor)), new RefundUpdated($refund->load('student.user', 'request'), 'requested'));

        return $refund;
    }

    public function approve(Refund $refund, User $actor, ?string $note = null): Refund
    {
        $refund = DB::transaction(function () use ($refund, $actor, $note) {
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($refund->status !== RefundStatus::Requested) {
                throw ValidationException::withMessages(['refund' => __('This refund is no longer awaiting approval.')]);
            }

            // Separation of duties, as for payment confirmation.
            if ($refund->requested_by !== null && $refund->requested_by === $actor->id) {
                throw ValidationException::withMessages(['refund' => __('You requested this refund, so someone else must approve it.')]);
            }

            $refund->update(['status' => RefundStatus::Approved, 'approved_by' => $actor->id, 'approved_at' => now(), 'decision_note' => $note]);
            $this->ledger->history($refund, RefundStatus::Requested->value, RefundStatus::Approved->value, $actor, $note);

            return $refund;
        });

        $this->notify($refund, 'approved', self::processors());

        return $refund;
    }

    /** Turn a refund down, any time before the money is paid out. */
    public function reject(Refund $refund, User $actor, string $reason): Refund
    {
        $refund = DB::transaction(function () use ($refund, $actor, $reason) {
            $request = PaymentRequest::whereKey($refund->payment_request_id)->lockForUpdate()->firstOrFail();
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if (! $refund->status->isOpen()) {
                throw ValidationException::withMessages(['refund' => __('This refund has already been :status.', ['status' => strtolower($refund->status->label())])]);
            }

            $from = $refund->status->value;
            $refund->update(['status' => RefundStatus::Rejected, 'decision_note' => $reason]);
            $this->ledger->history($refund, $from, RefundStatus::Rejected->value, $actor, $reason);
            $this->ledger->recalculate($request, $actor);

            return $refund;
        });

        $this->notify($refund, 'rejected');

        return $refund;
    }

    /**
     * Accounts has paid the money back. Idempotent: a refund that is not
     * awaiting payout is refused, so a double click cannot refund twice.
     */
    public function process(Refund $refund, User $actor, PaymentMethod $method, ?string $reference, ?string $notes): Refund
    {
        if ($method->kind === PaymentMethodKind::Gateway) {
            throw ValidationException::withMessages(['payment_method_id' => __('Choose how the money was actually returned (bank, mobile banking, cash…).')]);
        }

        if ($method->requires_reference && blank($reference)) {
            throw ValidationException::withMessages(['transaction_reference' => __('Enter the transaction ID or reference for the payout.')]);
        }

        $refund = DB::transaction(function () use ($refund, $actor, $method, $reference, $notes) {
            $request = PaymentRequest::whereKey($refund->payment_request_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($refund->status !== RefundStatus::Approved) {
                throw ValidationException::withMessages(['refund' => __('Only approved refunds can be paid out.')]);
            }

            $refund->update([
                'status' => RefundStatus::Processed,
                'processed_by' => $actor->id,
                'processed_at' => now(),
                'payment_method_id' => $method->id,
                'transaction_reference' => filled($reference) ? trim((string) $reference) : null,
                'notes' => $notes,
            ]);
            $this->ledger->history($refund, RefundStatus::Approved->value, RefundStatus::Processed->value, $actor, $notes);

            $payment->amount_refunded += $refund->amount;
            $payment->save();
            $request->amount_refunded += $refund->amount;
            $request->save();
            $this->ledger->recalculate($request, $actor);

            return $refund;
        });

        $this->notify($refund, 'processed');

        return $refund;
    }

    /**
     * People who may approve refunds. Super Admins pass every permission
     * check without holding the permission, so they are added by role.
     *
     * @return Collection<int, User>
     */
    public static function approvers(): Collection
    {
        return User::query()
            ->where('status', UserStatus::Active->value)
            ->where(fn (Builder $q) => $q->permission('refunds.approve')->orWhereHas('roles', fn (Builder $r) => $r->where('name', Role::SuperAdmin->value)))
            ->get();
    }

    /** @return Collection<int, User> */
    public static function processors(): Collection
    {
        return User::permission('refunds.process')->where('status', UserStatus::Active->value)->get();
    }

    /**
     * The student, their counselor and whoever asked always hear the result.
     *
     * @param  'approved'|'rejected'|'processed'  $event
     * @param  Collection<int, User>|null  $also
     */
    private function notify(Refund $refund, string $event, ?Collection $also = null): void
    {
        $refund->load(['student.user', 'student.counselor', 'requester', 'request']);

        $recipients = collect([$refund->student->user, $refund->student->counselor, $refund->requester])
            ->merge($also ?? [])
            ->filter()
            ->unique('id');

        Notification::send($recipients, new RefundUpdated($refund, $event));
    }
}
