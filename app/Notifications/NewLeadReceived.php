<?php

namespace App\Notifications;

use App\Models\Lead;

/** Tells lead managers a website enquiry arrived (REQ §11 steps 1–2). */
class NewLeadReceived extends AppNotification
{
    public function __construct(public readonly Lead $lead) {}

    protected function title(object $notifiable): string
    {
        return $this->lead->duplicate_of_id ? __('New enquiry (possible duplicate)') : __('New website enquiry');
    }

    protected function body(object $notifiable): string
    {
        return trim("{$this->lead->lead_number} · {$this->lead->name}".($this->lead->service ? " · {$this->lead->service->name}" : ''));
    }

    protected function url(object $notifiable): ?string
    {
        return route('admin.leads.show', $this->lead, absolute: false);
    }

    protected function icon(): string
    {
        return 'mark_email_unread';
    }

    protected function category(): string
    {
        return 'system';
    }
}
