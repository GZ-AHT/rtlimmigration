<?php

namespace App\Support\Presenters;

use App\Models\ConsultationBooking;
use App\Models\ConsultationBookingEvent;

class ConsultationPresenter
{
    private const ISO = 'Y-m-d\TH:i:s\Z';

    /** @return array<string, mixed> */
    public static function row(ConsultationBooking $b): array
    {
        return [
            'id' => $b->id,
            'reference' => $b->reference,
            'name' => $b->name,
            'email' => $b->email,
            'phone' => $b->phone,
            'subject' => $b->subject,
            'status' => $b->status->value,
            'status_label' => $b->status->label(),
            'status_tone' => $b->status->tone(),
            'starts_at' => $b->starts_at->format(self::ISO),
            'ends_at' => $b->ends_at->format(self::ISO),
            'duration_minutes' => $b->duration_minutes,
            'timezone' => $b->timezone,
            'meeting_url' => $b->meeting_url,
            'meeting_provider' => $b->meeting_provider,
            'has_user' => $b->user_id !== null,
            'has_lead' => $b->lead_id !== null,
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(ConsultationBooking $b): array
    {
        $b->loadMissing('events.user:id,name');

        return [
            ...self::row($b),
            'message' => $b->message,
            'meeting_sent_at' => $b->meeting_sent_at?->format(self::ISO),
            'confirmed_at' => $b->confirmed_at?->format(self::ISO),
            'cancelled_at' => $b->cancelled_at?->format(self::ISO),
            'customer_time' => $b->localTime(),
            'events' => $b->events->map(fn (ConsultationBookingEvent $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'note' => $e->note,
                'data' => $e->data,
                'by' => $e->user?->name,
                'at' => $e->created_at?->format(self::ISO),
            ])->values()->all(),
        ];
    }
}
