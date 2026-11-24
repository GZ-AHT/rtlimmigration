<?php

namespace App\Models;

use App\Enums\Area;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Support\Auth\Otp;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;
use Throwable;

/**
 * @property array<string, array{in_app?: bool, email?: bool}>|null $notification_preferences
 * @property int $id
 * @property string $name
 * @property string $email
 * @property-read string $contact_email the real address, also for deleted users
 * @property string|null $original_email the real address of a deleted user (`email` holds a placeholder)
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
     * A deleted user keeps its row (payments, files and history reference it)
     * but gives up its email, so the address can register again and uniqueness
     * still holds for every live account. Restoring takes the address back
     * unless somebody else registered it meanwhile.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->isForceDeleting() || $user->original_email !== null) {
                return;
            }

            $user->forceFill([
                'original_email' => $user->email,
                'email' => self::placeholderEmail((int) $user->getKey()),
            ])->saveQuietly();
        });

        static::restoring(function (User $user) {
            if ($user->original_email === null) {
                return;
            }

            if (self::query()->where('email', $user->original_email)->whereKeyNot($user->getKey())->exists()) {
                throw ValidationException::withMessages(['user' => __('This email address now belongs to another account, so this user cannot be restored.')]);
            }

            $user->email = $user->original_email;
            $user->original_email = null;
        });
    }

    /** An address that can never receive mail or collide with a real one. */
    public static function placeholderEmail(int $id): string
    {
        return "deleted-{$id}-".now()->timestamp.'@deleted.invalid';
    }

    /**
     * The person's real address, also for deleted users.
     *
     * @return Attribute<string, never>
     */
    protected function contactEmail(): Attribute
    {
        return Attribute::get(fn () => $this->original_email ?? $this->email);
    }

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
            'notification_preferences' => 'array',
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

    /** @return HasMany<BlogPost, $this> REQ §4 posts written by this user */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'author_id');
    }

    /** @return HasOne<AuthorProfile, $this> */
    public function authorProfile(): HasOne
    {
        return $this->hasOne(AuthorProfile::class);
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

    /**
     * Send the password reset notification safely.
     *
     * In local development or for RFC 2606 example domains (e.g. super-admin@example.com),
     * write the reset link directly to the log so developers/testers can test without
     * attempting to deliver to non-existent domains on a live SMTP server.
     *
     * If the mail server rejects delivery (e.g. 550 invalid domain, connection failure),
     * catch the exception and display a clear validation error instead of crashing.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = route('password.reset', ['token' => $token, 'email' => $this->email]);

        if (app()->environment('local', 'testing') && preg_match('/@example\.(com|net|org)$/i', $this->email)) {
            Log::info("Password reset requested for {$this->email}. Reset URL: {$resetUrl}");

            if (request()->hasSession()) {
                request()->session()->flash('toast', [
                    'type' => 'info',
                    'message' => __('Local dev: password reset link written to storage/logs/laravel.log'),
                ]);
            }

            // Live SMTP connections reject @example.com with 550. In local development,
            // record the URL in the log and return smoothly so the UI can proceed.
            if (config('mail.default') === 'smtp') {
                return;
            }
        }

        try {
            $this->notify(new ResetPasswordNotification($token));
        } catch (Throwable $e) {
            report($e);
            Log::warning("Failed to deliver password reset email to {$this->email}: {$e->getMessage()}", [
                'reset_url' => $resetUrl,
            ]);

            throw ValidationException::withMessages([
                'email' => __('We could not deliver the email to this address. Please ensure the email address is valid and can receive incoming mail.'),
            ]);
        }
    }

    /**
     * Email a six-digit verification code (not a link). Called after
     * registration, from "resend" and from the admin Users screen. A code sent
     * in the last 60 seconds is not repeated.
     */
    public function sendEmailVerificationNotification(): void
    {
        app(Otp::class)->send($this->email, Otp::VERIFY_EMAIL, $this);
    }
}
