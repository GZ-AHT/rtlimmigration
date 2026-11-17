<?php

namespace App\Notifications;

use App\Enums\Area;
use App\Models\ChatMessage;
use App\Models\User;

/** A new portal message: from a student to staff, or from staff to the student. */
class ChatMessageReceived extends AppNotification
{
    public function __construct(public readonly ChatMessage $message) {}

    protected function title(object $notifiable): string
    {
        return $this->message->sender_type === ChatMessage::STUDENT
            ? __('New message from :name', ['name' => $this->message->user->name ?? __('a student')])
            : __('New message from :name', ['name' => $this->message->user->name ?? __('RTL Immigration')]);
    }

    protected function body(object $notifiable): string
    {
        $text = (string) str((string) $this->message->body)->squish()->limit(80);

        return $text !== '' ? $text : trans_choice(':count attachment|:count attachments', $this->message->attachments->count());
    }

    protected function url(object $notifiable): ?string
    {
        $area = $notifiable instanceof User ? $notifiable->homeArea() : null;

        return match ($area) {
            Area::Portal => route('portal.chat', absolute: false),
            Area::Counselor => route('counselor.chat.show', $this->message->chat_conversation_id, absolute: false),
            default => route('admin.chat.show', $this->message->chat_conversation_id, absolute: false),
        };
    }

    protected function icon(): string
    {
        return 'chat';
    }

    protected function category(): string
    {
        return 'student';
    }
}
