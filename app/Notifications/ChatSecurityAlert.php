<?php

namespace App\Notifications;

/** REQ §11 H: suspicious chat activity, for Super Admin. Sent at most once per subject per alert window. */
class ChatSecurityAlert extends AppNotification
{
    public function __construct(public readonly string $headline, public readonly string $detail, public readonly string $target = 'activity') {}

    protected function title(object $notifiable): string
    {
        return $this->headline;
    }

    protected function body(object $notifiable): string
    {
        return $this->detail;
    }

    protected function url(object $notifiable): ?string
    {
        return $this->target === 'moderation'
            ? route('admin.chat.moderation', absolute: false)
            : route('admin.chat.security.activity', absolute: false);
    }

    protected function icon(): string
    {
        return 'shield';
    }
}
