<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something the chat security checks noticed: a rate limit, a blocked
 * message, a failed CAPTCHA, an unsafe file. Only visible to people with
 * security.manage, and pruned after the configured retention period.
 *
 * @property int $id
 * @property string $type
 * @property string $severity info, warning or critical
 * @property string|null $ip_address
 * @property int|null $user_id
 * @property int|null $chat_guest_id
 * @property int|null $chat_conversation_id
 * @property int|null $chat_message_id
 * @property array<string, mixed>|null $details
 * @property CarbonInterface|null $created_at
 * @property-read User|null $user
 * @property-read ChatGuest|null $guest
 */
#[Fillable(['type', 'severity', 'ip_address', 'user_id', 'chat_guest_id', 'chat_conversation_id', 'chat_message_id', 'details', 'created_at'])]
class ChatSecurityEvent extends Model
{
    public const UPDATED_AT = null;

    /** Event types and how they read in the security log. */
    public const TYPES = [
        'rate_limited' => 'Message limit reached',
        'cooldown' => 'Cooldown started',
        'duplicate' => 'Duplicate message',
        'repeated_spam' => 'Repeated message',
        'content_flagged' => 'Message flagged',
        'content_blocked' => 'Message blocked',
        'suspicious_link' => 'Suspicious link',
        'malicious_attachment' => 'Unsafe file',
        'invalid_attachment' => 'Rejected file',
        'blocked_ip' => 'Blocked IP address',
        'ip_blocked' => 'IP address blocked',
        'guest_suspended' => 'Visitor suspended',
        'honeypot' => 'Bot trap triggered',
        'bot_suspected' => 'Automated visitor suspected',
        'captcha_failed' => 'CAPTCHA failed',
        'conversation_limit' => 'Too many new chats',
    ];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return BelongsTo<ChatGuest, $this> */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(ChatGuest::class, 'chat_guest_id');
    }
}
