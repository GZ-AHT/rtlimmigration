<?php

namespace App\Notifications;

use App\Models\LeadFollowUp;
use App\Models\User;

/** Reminder that a lead follow-up is due (REQ §7 "Follow-up Date"). */
class FollowUpDue extends AppNotification
{
    public function __construct(public readonly LeadFollowUp $followUp) {}

    protected function title(object $notifiable): string
    {
        return __('Follow-up due');
    }

    protected function body(object $notifiable): string
    {
        $lead = $this->followUp->lead;

        return __(':name (:number)', ['name' => $lead->name, 'number' => $lead->lead_number])
            .($this->followUp->note ? ' · '.$this->followUp->note : '');
    }

    protected function url(object $notifiable): ?string
    {
        return $notifiable instanceof User && $notifiable->can('leads.assign')
            ? route('admin.leads.show', $this->followUp->lead_id, absolute: false)
            : route('counselor.leads.show', $this->followUp->lead_id, absolute: false);
    }

    protected function icon(): string
    {
        return 'event_repeat';
    }

    protected function category(): string
    {
        return 'counselor';
    }
}
