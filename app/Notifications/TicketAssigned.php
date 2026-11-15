<?php

namespace App\Notifications;

use App\Models\Ticket;

class TicketAssigned extends AppNotification
{
    public function __construct(public readonly Ticket $ticket) {}

    protected function title(object $notifiable): string
    {
        return __('Ticket assigned to you');
    }

    protected function body(object $notifiable): string
    {
        return "{$this->ticket->ticket_number} · {$this->ticket->subject}";
    }

    protected function url(object $notifiable): ?string
    {
        return route('admin.tickets.show', $this->ticket, absolute: false);
    }

    protected function icon(): string
    {
        return 'confirmation_number';
    }
}
