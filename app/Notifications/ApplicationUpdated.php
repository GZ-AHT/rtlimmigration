<?php

namespace App\Notifications;

use App\Models\ApplicationStatusHistory;
use App\Models\User;

/**
 * REQ §13.1–13.3: stage changes, decisions and office-visit requests reach
 * the student, and the counselor when someone else made the change.
 */
class ApplicationUpdated extends AppNotification
{
    public function __construct(public readonly ApplicationStatusHistory $history) {}

    protected function title(object $notifiable): string
    {
        $application = $this->history->application;

        return match ($this->history->event) {
            'created' => __('Application started'),
            'withdrawn' => __('Application withdrawn'),
            'decision' => $application->decision === 'approved' ? __('Visa approved') : __('Visa decision received'),
            default => __('Application update: :stage', ['stage' => $this->history->to_stage_name]),
        };
    }

    protected function body(object $notifiable): string
    {
        $application = $this->history->application;
        $base = $application->visaType->name.' · '.$application->application_number;

        return match ($this->history->event) {
            'created' => $base.' · '.$this->history->to_stage_name,
            // The reason is on the application page; a notification is no place for bad news in full.
            'decision' => $application->decision === 'approved'
                ? __(':base. Please see the details and next steps.', ['base' => $base])
                : __(':base. Please open your application for the details and next steps.', ['base' => $base]),
            default => filled($this->history->note) ? $base.': '.mb_substr((string) $this->history->note, 0, 140) : $base,
        };
    }

    protected function url(object $notifiable): ?string
    {
        if (! $notifiable instanceof User) {
            return null;
        }

        if ($notifiable->is($this->history->application->student->user)) {
            return route('portal.applications.status', absolute: false);
        }

        $area = $notifiable->homeArea()?->value;

        return in_array($area, ['admin', 'counselor'], true)
            ? route("{$area}.applications.show", $this->history->application_id, absolute: false)
            : null;
    }

    protected function icon(): string
    {
        return $this->history->event === 'decision' ? 'approval' : 'timeline';
    }

    protected function category(): string
    {
        return 'application';
    }
}
