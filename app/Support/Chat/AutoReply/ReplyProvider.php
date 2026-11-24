<?php

namespace App\Support\Chat\AutoReply;

use App\Models\ChatConversation;

/**
 * Decides the automatic answer to a visitor's message. The keyword matcher
 * is the only implementation today; an AI-powered provider can replace it by
 * binding this interface in the container, with nothing else changing.
 * Return null when there is no good answer: the service then sends the
 * friendly fallback.
 */
interface ReplyProvider
{
    public function reply(ChatConversation $conversation, string $message): ?string;
}
