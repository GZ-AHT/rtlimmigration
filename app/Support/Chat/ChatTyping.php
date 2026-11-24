<?php

namespace App\Support\Chat;

use App\Models\ChatConversation;
use App\Models\User;
use App\Support\Chat\AutoReply\SupportAgent;
use Illuminate\Support\Facades\Cache;

/**
 * "Agent is typing…" for the person on the other side. A staff member's
 * message box pings while they actually type; the entry expires on its own
 * after a few seconds, or when they send. Nothing is shown for the assistant.
 */
class ChatTyping
{
    private const TTL_SECONDS = 8;

    public static function agentTyping(ChatConversation $conversation, User $user): void
    {
        Cache::put(self::key($conversation), ['name' => SupportAgent::name($user), 'avatar' => SupportAgent::avatarUrl($user)], self::TTL_SECONDS);
    }

    public static function clear(ChatConversation $conversation): void
    {
        Cache::forget(self::key($conversation));
    }

    /** @return array{name: string, avatar: string|null}|null */
    public static function current(ChatConversation $conversation): ?array
    {
        $typing = Cache::get(self::key($conversation));

        if (! is_array($typing) || ! is_string($typing['name'] ?? null)) {
            return null;
        }

        return ['name' => $typing['name'], 'avatar' => is_string($typing['avatar'] ?? null) ? $typing['avatar'] : null];
    }

    private static function key(ChatConversation $conversation): string
    {
        return "chat.typing.{$conversation->id}";
    }
}
