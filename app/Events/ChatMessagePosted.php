<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A portal message was saved. Clients poll for now; this event already
 * names its private channel and payload, so moving to Laravel Reverb means
 * implementing ShouldBroadcast here and authorising the channel in
 * routes/channels.php, with no change to how messages are stored.
 */
class ChatMessagePosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("chat.conversation.{$this->message->chat_conversation_id}");
    }

    /** @return array{id: int, conversation_id: int} */
    public function broadcastWith(): array
    {
        // Only the ID: clients fetch the message through the authorised endpoint.
        return ['id' => $this->message->id, 'conversation_id' => $this->message->chat_conversation_id];
    }
}
