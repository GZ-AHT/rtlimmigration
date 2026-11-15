<?php

namespace App\Models;

use App\Enums\ContactChannel;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A prospective client (REQ §7, §11.1).
 *
 * @property int $id
 * @property string $lead_number
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $email_normalized
 * @property string|null $phone_normalized
 * @property int|null $country_id
 * @property int|null $service_id
 * @property ContactChannel|null $contact_preference
 * @property string|null $message
 * @property LeadSource $source
 * @property LeadStatus $status
 * @property int|null $counselor_id
 * @property CarbonInterface|null $assigned_at
 * @property CarbonInterface|null $next_follow_up_at
 * @property CarbonInterface|null $last_contacted_at
 * @property int|null $duplicate_of_id
 * @property int|null $student_id
 * @property CarbonInterface|null $converted_at
 * @property CarbonInterface|null $consent_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int|null $created_by
 * @property CarbonInterface|null $created_at
 * @property-read User|null $counselor
 * @property-read Country|null $country
 * @property-read Service|null $service
 * @property-read Student|null $student
 * @property-read Lead|null $duplicateOf
 */
#[Fillable([
    'lead_number', 'name', 'email', 'phone', 'email_normalized', 'phone_normalized', 'country_id', 'service_id',
    'contact_preference', 'message', 'source', 'status', 'counselor_id', 'assigned_at', 'next_follow_up_at',
    'last_contacted_at', 'duplicate_of_id', 'student_id', 'converted_at', 'consent_at', 'ip_address', 'user_agent', 'created_by',
])]
class Lead extends Model
{
    use LogsActivity, SoftDeletes;

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'contact_preference' => ContactChannel::class,
            'assigned_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'converted_at' => 'datetime',
            'consent_at' => 'datetime',
        ];
    }

    /** REQ §26: keep audit history of status, assignment and profile changes. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('leads')
            ->logOnly(['name', 'email', 'phone', 'country_id', 'service_id', 'status', 'counselor_id', 'next_follow_up_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<User, $this> */
    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id')->withTrashed();
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'duplicate_of_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** @return HasMany<LeadAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class)->latest('created_at')->latest('id');
    }

    /** @return HasMany<LeadFollowUp, $this> */
    public function followUps(): HasMany
    {
        return $this->hasMany(LeadFollowUp::class);
    }

    /** @return HasMany<LeadNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest()->latest('id');
    }

    /** @return HasMany<LeadContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(LeadContact::class)->latest('contacted_at')->latest('id');
    }

    /**
     * Leads the user may see. Anyone who can assign leads (Lead Manager,
     * Super Admin) sees all; counselors see only leads assigned to them
     * (REQ §8).
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('leads.assign')) {
            $query->where('counselor_id', $user->id);
        }
    }

    public static function normalizeEmail(?string $email): ?string
    {
        $email = $email !== null ? mb_strtolower(trim($email)) : null;

        return $email !== '' ? $email : null;
    }

    /** Last 10 digits, so "+880 1806-428212" and "01806428212" match. */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($digits) >= 7 ? substr($digits, -10) : null;
    }
}
