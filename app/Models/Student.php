<?php

namespace App\Models;

use App\Enums\StudentSource;
use Carbon\CarbonInterface;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The central student record (REQ §26 "One central student record").
 *
 * @property int $id
 * @property int $user_id
 * @property string $student_number
 * @property int|null $counselor_id
 * @property int|null $lead_id
 * @property string|null $phone
 * @property StudentSource $source
 * @property int|null $created_by
 * @property CarbonInterface|null $created_at
 * @property-read User $user
 * @property-read User|null $counselor
 */
#[Fillable(['user_id', 'student_number', 'counselor_id', 'lead_id', 'phone', 'source', 'created_by'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['source' => StudentSource::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasOne<StudentProfile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /** @return HasMany<StudentEducation, $this> */
    public function educations(): HasMany
    {
        return $this->hasMany(StudentEducation::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<StudentFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(StudentFile::class)->latest('opened_at')->latest('id');
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<PaymentRequest, $this> */
    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class)->latest()->latest('id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest()->latest('id');
    }

    /** @return HasMany<StudentNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(StudentNote::class)->latest()->latest('id');
    }

    /** @return HasMany<StudentCounselorAssignment, $this> */
    public function counselorAssignments(): HasMany
    {
        return $this->hasMany(StudentCounselorAssignment::class)->latest('assigned_at');
    }

    /**
     * Students the user may see (REQ §8: counselors only access their
     * assigned students unless granted more).
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('students.assign-counselor')) {
            $query->where('counselor_id', $user->id);
        }
    }
}
