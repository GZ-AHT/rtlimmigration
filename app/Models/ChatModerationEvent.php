<?php

namespace App\Models;

use App\Enums\ModerationStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change of a message's moderation status, by the automatic checks
 * (user_id null) or a moderator. Kept for audit; never pruned.
 *
 * @property int $id
 * @property int|null $chat_message_id
 * @property int|null $chat_conversation_id
 * @property ModerationStatus|null $from_status
 * @property ModerationStatus $to_status
 * @property list<string>|null $reasons
 * @property string|null $note
 * @property int|null $user_id
 * @property CarbonInterface|null $created_at
 * @property-read User|null $user
 */
#[Fillable(['chat_message_id', 'chat_conversation_id', 'from_status', 'to_status', 'reasons', 'note', 'user_id', 'created_at'])]
class ChatModerationEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_status' => ModerationStatus::class,
            'to_status' => ModerationStatus::class,
            'reasons' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
