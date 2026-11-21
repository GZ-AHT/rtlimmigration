<?php

namespace App\Notifications;

/** REQ §23.2 security notifications: lockouts and staff sign-ins from new addresses. */
class SecurityAlert extends AppNotification
{
    public function __construct(public readonly string $headline, public readonly string $detail) {}

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
        return route('admin.system.audit', absolute: false);
    }

    protected function icon(): string
    {
        return 'shield';
    }
}
