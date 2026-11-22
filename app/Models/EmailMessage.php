<?php

namespace App\Models;

use App\Enums\EmailMessageStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An email this system sent or will send (REQ §3 "Email Delivery Logs",
 * "Student Email History", "Email Tracking"). Mail sent from Roundcube is
 * not recorded here; it stays in the mailbox's Sent folder.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $email_account_id
 * @property int|null $email_campaign_id
 * @property int|null $email_template_id
 * @property int|null $sent_by
 * @property string|null $recipient_type
 * @property int|null $recipient_id
 * @property string $from_email
 * @property string|null $from_name
 * @property string $to_email
 * @property string|null $to_name
 * @property list<string>|null $cc
 * @property list<string>|null $bcc
 * @property string $subject
 * @property string $body
 * @property list<array{name: string, path: string, mime: string, size: int}>|null $attachments
 * @property EmailMessageStatus $status
 * @property string|null $error
 * @property CarbonInterface|null $sent_at
 * @property CarbonInterface|null $opened_at
 * @property int $open_count
 * @property CarbonInterface|null $clicked_at
 * @property int $click_count
 * @property CarbonInterface|null $trashed_at
 * @property CarbonInterface|null $created_at
 * @property-read EmailAccount|null $account
 * @property-read EmailCampaign|null $campaign
 * @property-read User|null $sender
 * @property-read Model|null $recipient
 */
#[Fillable([
    'uuid', 'email_account_id', 'email_campaign_id', 'email_template_id', 'sent_by', 'recipient_type', 'recipient_id',
    'from_email', 'from_name', 'to_email', 'to_name', 'cc', 'bcc', 'subject', 'body', 'attachments', 'status', 'error', 'sent_at',
    'opened_at', 'open_count', 'clicked_at', 'click_count', 'trashed_at',
])]
class EmailMessage extends Model
{
    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'cc' => 'array',
            'bcc' => 'array',
            'trashed_at' => 'datetime',
            'status' => EmailMessageStatus::class,
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'open_count' => 'integer',
            'click_count' => 'integer',
        ];
    }

    /** @return BelongsTo<EmailAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'email_account_id');
    }

    /** @return BelongsTo<EmailCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** @return MorphTo<Model, $this> */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }
}
