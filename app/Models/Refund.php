<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Money returned to a student, always against one confirmed payment
 * (REQ §12.5 rule 8).
 *
 * @property int $id
 * @property string $refund_number
 * @property int $payment_id
 * @property int $payment_request_id
 * @property int $student_id
 * @property int $amount
 * @property string $currency
 * @property string $reason
 * @property RefundStatus $status
 * @property int|null $requested_by
 * @property int|null $approved_by
 * @property CarbonInterface|null $approved_at
 * @property string|null $decision_note
 * @property int|null $processed_by
 * @property CarbonInterface|null $processed_at
 * @property int|null $payment_method_id
 * @property string|null $transaction_reference
 * @property string|null $notes
 * @property CarbonInterface|null $created_at
 * @property-read Payment $payment
 * @property-read PaymentRequest $request
 * @property-read Student $student
 * @property-read PaymentMethod|null $method
 * @property-read User|null $requester
 * @property-read User|null $approver
 * @property-read User|null $processor
 */
#[Fillable([
    'refund_number', 'payment_id', 'payment_request_id', 'student_id', 'amount', 'currency', 'reason', 'status',
    'requested_by', 'approved_by', 'approved_at', 'decision_note', 'processed_by', 'processed_at',
    'payment_method_id', 'transaction_reference', 'notes',
])]
class Refund extends Model
{
    protected $attributes = ['status' => 'requested', 'currency' => 'BDT'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => RefundStatus::class,
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<PaymentRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class, 'payment_request_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by')->withTrashed();
    }

    /** @return MorphMany<PaymentStatusHistory, $this> */
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(PaymentStatusHistory::class, 'subject')->latest('created_at')->latest('id');
    }

    /**
     * Finance and approvers see every refund; counselors see their own
     * students'.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! self::seesAll($user)) {
            $query->whereHas('student', fn (Builder $q) => $q->where('counselor_id', $user->id));
        }
    }

    public static function seesAll(User $user): bool
    {
        return PaymentRequest::seesAll($user) || $user->can('refunds.approve') || $user->can('refunds.process');
    }
}
