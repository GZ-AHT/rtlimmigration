<?php

namespace App\Actions\Chat;

use App\Enums\ModerationStatus;
use App\Models\ChatMessage;
use App\Models\ChatModerationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A moderator approves or rejects a message. The message is never deleted:
 * its status changes, the decision and reason are kept in the moderation
 * history and the activity log, and open threads pick up the change on
 * their next poll. Releasing a held message notifies the other side.
 */
class ModerateMessage
{
    public function __construct(private PortalMessaging $messaging) {}

    public function __invoke(ChatMessage $message, ModerationStatus $decision, User $moderator, ?string $note): ChatMessage
    {
        if (! in_array($decision, [ModerationStatus::Approved, ModerationStatus::Rejected], true)) {
            throw new InvalidArgumentException('A moderator can only approve or reject a message.');
        }

        $from = $message->moderation_status;

        if ($from === $decision) {
            return $message;
        }

        DB::transaction(function () use ($message, $decision, $moderator, $note, $from) {
            $message->forceFill([
                'moderation_status' => $decision,
                'reviewed_by' => $moderator->id,
                'reviewed_at' => now(),
            ])->save();

            ChatModerationEvent::query()->create([
                'chat_message_id' => $message->id,
                'chat_conversation_id' => $message->chat_conversation_id,
                'from_status' => $from,
                'to_status' => $decision,
                'reasons' => $message->moderation_reasons,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'user_id' => $moderator->id,
                'created_at' => now(),
            ]);

            $this->messaging->resequence($message);
        });

        activity('chat-security')->causedBy($moderator)->performedOn($message)->withProperties([
            'from' => $from->value,
            'to' => $decision->value,
            'conversation_id' => $message->chat_conversation_id,
        ])->log($decision === ModerationStatus::Approved ? 'Chat message approved' : 'Chat message rejected');

        if ($from->isHeld() && ! $decision->isHeld()) {
            $this->messaging->released($message);
        }

        return $message;
    }
}
