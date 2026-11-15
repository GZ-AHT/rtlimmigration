<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\User;

/** Tells a counselor a lead was assigned or transferred to them (REQ §8 "Receive leads"). */
class LeadAssigned extends AppNotification
{
    public function __construct(public readonly Lead $lead, public readonly bool $transfer = false) {}

    protected function title(object $notifiable): string
    {
        return $this->transfer ? __('Lead transferred to you') : __('New lead assigned');
    }

    protected function body(object $notifiable): string
    {
        return __(':number · :name', ['number' => $this->lead->lead_number, 'name' => $this->lead->name]);
    }

    protected function url(object $notifiable): ?string
    {
        return $notifiable instanceof User && $notifiable->can('leads.assign')
            ? route('admin.leads.show', $this->lead, absolute: false)
            : route('counselor.leads.show', $this->lead, absolute: false);
    }

    protected function icon(): string
    {
        return 'person_search';
    }

    protected function category(): string
    {
        return 'counselor';
    }
}
