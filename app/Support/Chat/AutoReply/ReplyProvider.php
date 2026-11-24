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
    /** True when the visitor asks for a person, or the provider decides a person is needed. */
    public function needsHuman(ChatConversation $conversation, string $message): bool;

    public function reply(ChatConversation $conversation, string $message): ?string;
}
