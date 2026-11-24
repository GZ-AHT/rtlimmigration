<?php

namespace App\Notifications;

use App\Models\ConsultationBooking;

/** Tells the team a customer cancelled their video consultation. */
class ConsultationCancelled extends AppNotification
{
    public function __construct(public readonly ConsultationBooking $booking) {}

    protected function title(object $notifiable): string
    {
        return __('Consultation cancelled by the customer');
    }

    protected function body(object $notifiable): string
    {
        return "{$this->booking->reference} · {$this->booking->name} · ".$this->booking->starts_at->format('j M Y, H:i').' UTC';
    }

    protected function url(object $notifiable): ?string
    {
        return route('admin.consultations.show', $this->booking, absolute: false);
    }

    protected function icon(): string
    {
        return 'event_busy';
    }
}
