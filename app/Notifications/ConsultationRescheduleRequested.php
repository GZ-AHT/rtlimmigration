<?php

namespace App\Notifications;

use App\Models\ConsultationBooking;

/** Tells the team a customer asked for another time. */
class ConsultationRescheduleRequested extends AppNotification
{
    public function __construct(public readonly ConsultationBooking $booking, public readonly string $request) {}

    protected function title(object $notifiable): string
    {
        return __('Consultation reschedule requested');
    }

    protected function body(object $notifiable): string
    {
        return "{$this->booking->reference} · {$this->booking->name}: ".str($this->request)->squish()->limit(120);
    }

    protected function url(object $notifiable): ?string
    {
        return route('admin.consultations.show', $this->booking, absolute: false);
    }

    protected function icon(): string
    {
        return 'edit_calendar';
    }
}
