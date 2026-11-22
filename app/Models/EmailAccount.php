<?php

namespace App\Models;

use App\Enums\EmailAccountStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A mailbox on the cPanel mail server (REQ §19.2). The password is kept
 * encrypted and only used server-side for SMTP; it never reaches the browser.
 *
 * @property int $id
 * @property string $email
 * @property string $local_part
 * @property string|null $username
 * @property string $domain
 * @property string|null $display_name
 * @property string|null $password
 * @property int|null $quota_mb
 * @property float|null $disk_used_mb
 * @property EmailAccountStatus $status
 * @property bool $on_server
 * @property CarbonInterface|null $synced_at
 * @property int|null $created_by
 * @property-read Collection<int, User> $users
 */
#[Fillable(['email', 'local_part', 'username', 'domain', 'display_name', 'password', 'quota_mb', 'disk_used_mb', 'status', 'on_server', 'synced_at', 'created_by'])]
#[Hidden(['password'])]
class EmailAccount extends Model
{
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'quota_mb' => 'integer',
            'disk_used_mb' => 'float',
            'status' => EmailAccountStatus::class,
            'on_server' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['can_send', 'assigned_by'])->withTimestamps();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<EmailMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    /** @param Builder<self> $query */
    public function scopeAssignedTo(Builder $query, User $user): void
    {
        $query->whereHas('users', fn (Builder $q) => $q->whereKey($user->id));
    }

    public function isActive(): bool
    {
        return $this->status === EmailAccountStatus::Active;
    }

    /** SMTP sending needs the mailbox password, which an imported mailbox may not have yet. */
    public function canSendSmtp(): bool
    {
        return $this->isActive() && filled($this->password);
    }

    /** What the mailbox signs in with (IMAP and SMTP): its own username, or its address. */
    public function loginName(): string
    {
        return filled($this->username) ? (string) $this->username : $this->email;
    }

    public function fromName(): string
    {
        return $this->display_name ?: (string) config('app.name');
    }
}
