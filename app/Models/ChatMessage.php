<?php

namespace App\Models;

use App\Enums\ModerationStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One message in a portal thread or a website (guest) chat.
 *
 * @property int $id
 * @property int $chat_conversation_id
 * @property int $seq position in the conversation's change sequence (polling cursor)
 * @property int|null $user_id
 * @property string $sender_type student, visitor, staff or system
 * @property string|null $body
 * @property string|null $client_id
 * @property ModerationStatus $moderation_status
 * @property list<string>|null $moderation_reasons
 * @property int|null $reviewed_by
 * @property CarbonInterface|null $reviewed_at
 * @property CarbonInterface|null $created_at
 * @property-read ChatConversation $conversation
 * @property-read User|null $user
 * @property-read Collection<int, ChatMessageAttachment> $attachments
 */
#[Fillable(['chat_conversation_id', 'seq', 'user_id', 'sender_type', 'body', 'client_id', 'moderation_status', 'moderation_reasons', 'reviewed_by', 'reviewed_at'])]
class ChatMessage extends Model
{
    public const STUDENT = 'student';

    /** A website visitor without an account (guest chat). */
    public const VISITOR = 'visitor';

    public const STAFF = 'staff';

    public const SYSTEM = 'system';

    /** Senders whose messages go through the content checks and moderation. */
    public const MODERATED_SENDERS = [self::STUDENT, self::VISITOR];

    protected $attributes = ['moderation_status' => 'approved'];

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'moderation_status' => ModerationStatus::class,
            'moderation_reasons' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ChatConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return HasMany<ChatMessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ChatMessageAttachment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    /** @return HasMany<ChatModerationEvent, $this> */
    public function moderationEvents(): HasMany
    {
        return $this->hasMany(ChatModerationEvent::class);
    }

    public function isHeld(): bool
    {
        return $this->moderation_status->isHeld();
    }
}
