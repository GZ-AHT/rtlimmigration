<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One payment made against a request: manual evidence now, gateway
 * transactions in Phase 5 (REQ §12.1 steps 4–8).
 *
 * @property int $id
 * @property int $payment_request_id
 * @property int $student_id
 * @property int $payment_method_id
 * @property int $amount
 * @property int $amount_refunded
 * @property string $currency
 * @property string|null $transaction_reference
 * @property CarbonInterface $paid_on
 * @property string|null $evidence_disk
 * @property string|null $evidence_path
 * @property string|null $evidence_original_name
 * @property string|null $evidence_mime_type
 * @property PaymentStatus $status
 * @property int|null $submitted_by
 * @property int|null $verified_by
 * @property CarbonInterface|null $verified_at
 * @property int|null $confirmed_by
 * @property CarbonInterface|null $confirmed_at
 * @property string|null $rejection_reason
 * @property string|null $receipt_number
 * @property string|null $notes
 * @property string|null $internal_note
 * @property CarbonInterface|null $created_at
 * @property-read PaymentRequest $request
 * @property-read Student $student
 * @property-read PaymentMethod $method
 * @property-read User|null $submitter
 * @property-read User|null $verifier
 * @property-read User|null $confirmer
 */
#[Fillable([
    'payment_request_id', 'student_id', 'payment_method_id', 'amount', 'amount_refunded', 'currency', 'transaction_reference', 'paid_on',
    'evidence_disk', 'evidence_path', 'evidence_original_name', 'evidence_mime_type', 'status', 'submitted_by',
    'verified_by', 'verified_at', 'confirmed_by', 'confirmed_at', 'rejection_reason', 'receipt_number', 'notes', 'internal_note',
])]
class Payment extends Model
{
    protected $attributes = ['status' => 'pending', 'currency' => 'BDT', 'amount_refunded' => 0];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'amount_refunded' => 'integer',
            'paid_on' => 'date',
            'status' => PaymentStatus::class,
            'verified_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
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
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by')->withTrashed();
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->latest()->latest('id');
    }

    /** What can still be refunded: not already refunded or held by an open refund. */
    public function refundable(): int
    {
        if ($this->status !== PaymentStatus::Confirmed) {
            return 0;
        }

        $held = (int) $this->refunds()->whereIn('status', ['requested', 'approved'])->sum('amount');

        return max(0, $this->amount - $this->amount_refunded - $held);
    }

    /** @return MorphMany<PaymentStatusHistory, $this> */
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(PaymentStatusHistory::class, 'subject')->latest('created_at')->latest('id');
    }

    /** @param  Builder<self>  $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! PaymentRequest::seesAll($user)) {
            $query->whereHas('student', fn (Builder $q) => $q->where('counselor_id', $user->id));
        }
    }
}
