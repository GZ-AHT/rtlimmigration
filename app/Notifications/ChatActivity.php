<?php

namespace App\Notifications;

use App\Enums\Area;
use App\Models\ChatConversation;
use App\Models\User;

/** REQ §18 "Chat Notifications": a student's chat, an offline message, or an assignment. */
class ChatActivity extends AppNotification
{
    /** @param  'started'|'offline'|'assigned'  $what */
    public function __construct(public readonly ChatConversation $conversation, public readonly string $what) {}

    protected function title(object $notifiable): string
    {
        return match ($this->what) {
            'started' => __('Your student started a live chat'),
            'offline' => __('New offline chat message'),
            default => __('Chat assigned to you'),
        };
    }

    protected function body(object $notifiable): string
    {
        $who = $this->conversation->visitorLabel();
        $text = $this->conversation->subject ?: $this->conversation->first_message;

        return $text ? $who.' · '.str($text)->squish()->limit(80) : $who;
    }

    protected function url(object $notifiable): ?string
    {
        $area = $notifiable instanceof User && $notifiable->homeArea() === Area::Counselor ? 'counselor' : 'admin';

        return route("{$area}.chat.show", $this->conversation, absolute: false);
    }

    protected function icon(): string
    {
        return $this->conversation->kind->icon();
    }

    protected function category(): string
    {
        return 'student';
    }
}
