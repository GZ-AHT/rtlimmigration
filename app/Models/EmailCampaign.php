<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One bulk send (REQ §3 "Bulk Email"). Each recipient gets their own
 * EmailMessage, sent through the queue.
 *
 * @property int $id
 * @property string $name
 * @property int|null $email_account_id
 * @property int|null $email_template_id
 * @property string $subject
 * @property string $body
 * @property array<string, mixed> $audience
 * @property int $total
 * @property int $sent
 * @property int $failed
 * @property int|null $created_by
 * @property CarbonInterface|null $created_at
 * @property-read EmailAccount|null $account
 * @property-read User|null $creator
 */
#[Fillable(['name', 'email_account_id', 'email_template_id', 'subject', 'body', 'audience', 'total', 'sent', 'failed', 'created_by'])]
class EmailCampaign extends Model
{
    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'total' => 'integer',
            'sent' => 'integer',
            'failed' => 'integer',
        ];
    }

    /** @return BelongsTo<EmailAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'email_account_id');
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
}
