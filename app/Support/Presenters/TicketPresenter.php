<?php

namespace App\Support\Presenters;

use App\Models\Ticket;
use App\Models\TicketReply;

class TicketPresenter
{
    /** @return array<string, mixed> */
    public static function row(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'requester' => $ticket->requesterName(),
            'requester_email' => $ticket->requesterEmail(),
            'category' => $ticket->category?->name,
            'priority' => $ticket->priority->value,
            'priority_label' => $ticket->priority->label(),
            'priority_tone' => $ticket->priority->tone(),
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'status_tone' => $ticket->status->tone(),
            'assignee' => $ticket->assignee?->name,
            'last_reply_at' => $ticket->last_reply_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Ticket $ticket): array
    {
        return [
            ...self::row($ticket),
            'description' => $ticket->description,
            'category_id' => $ticket->category_id,
            'assigned_to' => $ticket->assigned_to,
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'replies' => $ticket->replies->map(fn (TicketReply $r) => [
                'id' => $r->id,
                'body' => $r->body,
                'internal' => $r->is_internal,
                'emailed' => $r->emailed_at !== null,
                'author' => $r->user?->name,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
