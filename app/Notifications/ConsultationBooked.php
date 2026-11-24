<?php

namespace App\Notifications;

use App\Models\ConsultationBooking;

/** Tells the team a visitor booked a video consultation. */
class ConsultationBooked extends AppNotification
{
    public function __construct(public readonly ConsultationBooking $booking) {}

    protected function title(object $notifiable): string
    {
        return __('New consultation booking');
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
        return 'video_call';
    }
}
