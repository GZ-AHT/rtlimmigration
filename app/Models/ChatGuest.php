<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A website visitor chatting without an account. They are identified by a
 * random token in an encrypted, HTTP-only cookie; only its SHA-256 hash is
 * stored, and the IP address is kept as security metadata, never as the
 * identity.
 *
 * @property int $id
 * @property int|null $chat_conversation_id
 * @property string $token_hash
 * @property string $name
 * @property string|null $email
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonInterface|null $suspended_until
 * @property string|null $suspension_reason
 * @property int|null $linked_user_id
 * @property CarbonInterface|null $linked_at
 * @property CarbonInterface|null $last_seen_at
 * @property CarbonInterface|null $created_at
 * @property-read ChatConversation|null $conversation
 * @property-read User|null $linkedUser
 */
#[Fillable(['chat_conversation_id', 'token_hash', 'name', 'email', 'ip_address', 'user_agent', 'suspended_until', 'suspension_reason', 'linked_user_id', 'linked_at', 'last_seen_at'])]
#[Hidden(['token_hash', 'ip_address', 'user_agent'])]
class ChatGuest extends Model
{
    public const COOKIE = 'rtl_chat_guest';

    protected function casts(): array
    {
        return [
            'suspended_until' => 'datetime',
            'linked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_until !== null && $this->suspended_until->isFuture();
    }

    /** @return BelongsTo<ChatConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id')->withTrashed();
    }
}
