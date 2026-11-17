<?php

namespace App\Models;

use App\Enums\Area;
use App\Enums\Role;
use App\Enums\UserStatus;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonInterface|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonInterface|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property UserStatus $status
 * @property CarbonInterface|null $last_login_at
 * @property string|null $last_login_ip
 * @property CarbonInterface|null $deleted_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'status'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Defaults for new models, matching the column defaults, so a user is
     * active before it has been reloaded from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * REQ §6 "User Activity Logs": profile and status changes are recorded.
     * Passwords and 2FA secrets are never logged.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['name', 'email', 'status', 'email_verified_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return HasMany<LoginHistory, $this> */
    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    /** @return HasOne<Counselor, $this> */
    public function counselorProfile(): HasOne
    {
        return $this->hasOne(Counselor::class);
    }

    /** @return BelongsToMany<EmailAccount, $this> REQ §19.2 mailboxes this user may open */
    public function emailAccounts(): BelongsToMany
    {
        return $this->belongsToMany(EmailAccount::class)->withPivot(['can_send'])->withTimestamps();
    }

    /** @return HasOne<EmailSignature, $this> */
    public function emailSignature(): HasOne
    {
        return $this->hasOne(EmailSignature::class);
    }

    /** @return HasOne<Student, $this> */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /** @return HasMany<Lead, $this> */
    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'counselor_id');
    }

    /** @return HasMany<Student, $this> */
    public function assignedStudents(): HasMany
    {
        return $this->hasMany(Student::class, 'counselor_id');
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SuperAdmin->value);
    }

    /**
     * The highest-priority built-in role the user holds, in the order the
     * Role enum declares them. Null when the user has no built-in role.
     */
    public function primaryRole(): ?Role
    {
        $names = $this->getRoleNames();

        foreach (Role::cases() as $role) {
            if ($names->contains($role->value)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * The dashboard area the user should land in after login.
     */
    public function homeArea(): ?Area
    {
        if ($area = $this->primaryRole()?->area()) {
            return $area;
        }

        foreach (Area::cases() as $area) {
            if ($this->can($area->accessPermission())) {
                return $area;
            }
        }

        return null;
    }
}
