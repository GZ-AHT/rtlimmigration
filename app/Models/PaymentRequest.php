<?php

namespace App\Models;

use App\Enums\PaymentRequestStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A bill raised against a student (REQ §12.1 steps 1–3). Its invoice
 * number is unique (REQ §12.5 rule 3).
 *
 * @property int $id
 * @property string $invoice_number
 * @property int $student_id
 * @property int|null $student_file_id
 * @property int $payment_type_id
 * @property string|null $description
 * @property int $amount
 * @property int $amount_paid
 * @property string $currency
 * @property CarbonInterface|null $due_date
 * @property PaymentRequestStatus $status
 * @property int|null $requested_by
 * @property string|null $notes
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancel_reason
 * @property CarbonInterface|null $created_at
 * @property-read Student $student
 * @property-read StudentFile|null $file
 * @property-read PaymentType $type
 * @property-read User|null $requester
 */
#[Fillable([
    'invoice_number', 'student_id', 'student_file_id', 'payment_type_id', 'description', 'amount', 'amount_paid',
    'currency', 'due_date', 'status', 'requested_by', 'notes', 'paid_at', 'cancelled_at', 'cancelled_by', 'cancel_reason',
])]
class PaymentRequest extends Model
{
    protected $attributes = ['status' => 'unpaid', 'amount_paid' => 0, 'currency' => 'BDT'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'amount_paid' => 'integer',
            'due_date' => 'date',
            'status' => PaymentRequestStatus::class,
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<StudentFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(StudentFile::class, 'student_file_id');
    }

    /** @return BelongsTo<PaymentType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class, 'payment_type_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest()->latest('id');
    }

    /** @return MorphMany<PaymentStatusHistory, $this> */
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(PaymentStatusHistory::class, 'subject')->latest('created_at')->latest('id');
    }

    public function outstanding(): int
    {
        return max(0, $this->amount - $this->amount_paid);
    }

    /**
     * Finance staff (verify/confirm permission) and those who can assign
     * students see every request; counselors see their own students';
     * students only reach their own through the portal.
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
        return $user->can('payments.verify') || $user->can('payments.confirm') || $user->can('students.assign-counselor');
    }
}
