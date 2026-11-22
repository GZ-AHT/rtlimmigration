<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message received in a cPanel mailbox, copied by the IMAP sync.
 *
 * @property int $id
 * @property int $email_account_id
 * @property string $folder
 * @property string $remote_folder
 * @property int $uid
 * @property string|null $message_id
 * @property string $dedupe_key
 * @property string|null $from_email
 * @property string|null $from_name
 * @property list<string>|null $to
 * @property list<string>|null $cc
 * @property string|null $reply_to
 * @property string|null $subject
 * @property string|null $preview
 * @property string|null $body_text
 * @property string|null $body_html
 * @property list<array{name: string, path: string, mime: string, size: int, cid: string|null, inline: bool}>|null $attachments
 * @property bool $has_attachments
 * @property bool $is_read
 * @property CarbonInterface|null $received_at
 * @property-read EmailAccount $account
 */
class MailboxMessage extends Model
{
    public const FOLDERS = ['inbox', 'drafts', 'spam', 'trash'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'to' => 'array',
            'cc' => 'array',
            'attachments' => 'array',
            'has_attachments' => 'boolean',
            'is_read' => 'boolean',
            'received_at' => 'datetime',
            'uid' => 'integer',
        ];
    }

    /** @return BelongsTo<EmailAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'email_account_id');
    }

    /** Only mailboxes assigned to the user (everyone with email.manage sees all). */
    /** @param Builder<self> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('email.manage')) {
            return;
        }

        $query->whereIn('email_account_id', $user->emailAccounts()->select('email_accounts.id'));
    }
}
