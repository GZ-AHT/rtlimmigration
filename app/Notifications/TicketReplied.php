<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** REQ §7 "Reply via Email": emails a staff reply to the ticket requester. */
class TicketReplied extends Notification
{
    use Queueable;

    public function __construct(public readonly Ticket $ticket, public readonly TicketReply $reply) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->ticket->ticket_number}] {$this->ticket->subject}")
            ->greeting(__('Hello :name,', ['name' => $this->ticket->requesterName()]))
            ->line(__(':agent replied to your request:', ['agent' => $this->reply->user->name ?? config('app.name')]))
            ->line($this->reply->body)
            ->line(__('Ticket :number · Status: :status', ['number' => $this->ticket->ticket_number, 'status' => $this->ticket->status->label()]))
            ->line(__('To reply, contact us and quote the ticket number above.'));
    }
}
