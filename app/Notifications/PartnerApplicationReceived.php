<?php

namespace App\Notifications;

use App\Models\PartnerApplication;

/** Tells partner reviewers a website partner application arrived (REQ §16). */
class PartnerApplicationReceived extends AppNotification
{
    public function __construct(public readonly PartnerApplication $application) {}

    protected function title(object $notifiable): string
    {
        return __('New partner application');
    }

    protected function body(object $notifiable): string
    {
        return "{$this->application->organisation} · {$this->application->contact_name}";
    }

    protected function url(object $notifiable): ?string
    {
        return route('admin.partner-applications.show', $this->application, absolute: false);
    }

    protected function icon(): string
    {
        return 'handshake';
    }

    protected function category(): string
    {
        return 'system';
    }
}
