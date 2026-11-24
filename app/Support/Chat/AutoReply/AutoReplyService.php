<?php

namespace App\Support\Chat\AutoReply;

use App\Actions\Chat\PortalMessaging;
use App\Enums\ChatKind;
use App\Enums\ModerationStatus;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Support\Settings\SettingsRepository;
use Ramsey\Uuid\Uuid;

/**
 * Answers a website visitor's message with a predefined reply (REQ §11).
 *
 * Rules that keep it polite and safe:
 *  - only website (guest) chats, only approved messages;
 *  - never once a person from the team has replied in the chat;
 *  - one reply per visitor message (the reply's client ID comes from the
 *    message, so a retry or a race cannot add a second one);
 *  - never the same text twice in a row, and an hourly cap per chat.
 * Replies are stored as "bot" messages, so they are never shown as a person.
 */
class AutoReplyService
{
    /** Most automatic replies one chat gets per hour. */
    private const PER_HOUR = 30;

    public function __construct(
        private AutoReplySettings $settings,
        private ReplyProvider $provider,
        private PortalMessaging $messaging,
        private SettingsRepository $site,
    ) {}

    public function respond(ChatConversation $conversation, ChatMessage $visitorMessage): ?ChatMessage
    {
        if (! $this->settings->enabled()
            || $conversation->kind !== ChatKind::Guest
            || $visitorMessage->sender_type !== ChatMessage::VISITOR
            || $visitorMessage->moderation_status !== ModerationStatus::Approved
            || blank($visitorMessage->body)) {
            return null;
        }

        $messages = $conversation->chatMessages();

        // A person has joined: stay out of the way.
        if ((clone $messages)->where('sender_type', ChatMessage::STAFF)->exists()) {
            return null;
        }

        if ((clone $messages)->where('sender_type', ChatMessage::BOT)->where('created_at', '>=', now()->subHour())->count() >= self::PER_HOUR) {
            return null;
        }

        $body = $this->render($this->provider->reply($conversation, (string) $visitorMessage->body) ?? $this->settings->fallback());

        if ($body === '') {
            return null;
        }

        $last = (clone $messages)->where('sender_type', ChatMessage::BOT)->latest('id')->value('body');

        if ($last === $body) {
            return null;
        }

        return $this->messaging->automated($conversation, $body, Uuid::uuid5(Uuid::NAMESPACE_URL, "chat-auto-reply:{$visitorMessage->id}")->toString());
    }

    /** How long the typing indicator shows before the reply appears, in milliseconds. */
    public static function typingMs(string $body): int
    {
        return max(1200, min(3200, 900 + mb_strlen($body) * 18));
    }

    private function render(string $text): string
    {
        return trim(strtr($text, [
            '{site_name}' => (string) $this->site->get('general.site_name', config('app.name')),
            '{phone}' => (string) $this->site->get('general.phone', ''),
            '{email}' => (string) $this->site->get('general.contact_email', ''),
        ]));
    }
}
