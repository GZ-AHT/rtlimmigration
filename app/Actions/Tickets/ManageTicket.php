<?php

namespace App\Actions\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketReplied;
use App\Support\Numbering\NumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** REQ §7 ticket workflow: create, reply, internal notes, assign, status and priority. */
class ManageTicket
{
    public function __construct(private NumberGenerator $numbers) {}

    /**
     * @param  array{subject: string, description: string, category_id?: int|null, priority: string, requester_id?: int|null, requester_name?: string|null, requester_email?: string|null, assigned_to?: int|null}  $data
     */
    public function create(array $data, User $actor): Ticket
    {
        $ticket = DB::transaction(fn () => Ticket::create([
            'ticket_number' => $this->numbers->next('ticket'),
            'subject' => $data['subject'],
            'description' => $data['description'],
            'category_id' => $data['category_id'] ?? null,
            'priority' => $data['priority'],
            'status' => TicketStatus::Open,
            'requester_id' => $data['requester_id'] ?? null,
            'requester_name' => empty($data['requester_id']) ? ($data['requester_name'] ?? null) : null,
            'requester_email' => empty($data['requester_id']) ? ($data['requester_email'] ?? null) : null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'created_by' => $actor->id,
        ]));

        $this->notifyAssignee($ticket, $actor);

        return $ticket;
    }

    /**
     * @param  array{category_id?: int|null, priority?: string, status?: string, assigned_to?: int|null}  $changes
     */
    public function update(Ticket $ticket, array $changes, User $actor): void
    {
        $previousAssignee = $ticket->assigned_to;

        DB::transaction(function () use ($ticket, $changes) {
            if (array_key_exists('category_id', $changes)) {
                $ticket->category_id = $changes['category_id'];
            }

            if (isset($changes['priority'])) {
                $ticket->priority = TicketPriority::from($changes['priority']);
            }

            if (array_key_exists('assigned_to', $changes)) {
                $ticket->assigned_to = $changes['assigned_to'];
            }

            if (isset($changes['status'])) {
                $this->applyStatus($ticket, TicketStatus::from($changes['status']));
            }

            $ticket->save();
        });

        if ($ticket->assigned_to !== $previousAssignee) {
            $this->notifyAssignee($ticket, $actor);
        }
    }

    /**
     * Adds a reply (emailed to the requester) or an internal note (staff
     * only, never emailed). A reply can also change the status.
     */
    public function reply(Ticket $ticket, User $actor, string $body, bool $internal, ?TicketStatus $status = null): TicketReply
    {
        $reply = DB::transaction(function () use ($ticket, $actor, $body, $internal, $status) {
            $reply = $ticket->replies()->create([
                'user_id' => $actor->id,
                'body' => $body,
                'is_internal' => $internal,
            ]);

            if (! $internal) {
                $ticket->last_reply_at = now();
                // Replying to an open ticket means we're waiting on the requester.
                $this->applyStatus($ticket, $status ?? ($ticket->status === TicketStatus::Open ? TicketStatus::Pending : $ticket->status));
            } elseif ($status) {
                $this->applyStatus($ticket, $status);
            }

            $ticket->save();

            return $reply;
        });

        $email = $ticket->requesterEmail();

        if (! $internal && $email) {
            $ticket->requester
                ? $ticket->requester->notify(new TicketReplied($ticket, $reply))
                : Notification::route('mail', [$email => $ticket->requesterName()])->notify(new TicketReplied($ticket, $reply));

            $reply->update(['emailed_at' => now()]);
        }

        return $reply;
    }

    private function applyStatus(Ticket $ticket, TicketStatus $status): void
    {
        $ticket->status = $status;
        $ticket->resolved_at = $status === TicketStatus::Resolved ? ($ticket->resolved_at ?? now()) : ($status === TicketStatus::Closed ? $ticket->resolved_at : null);
        $ticket->closed_at = $status === TicketStatus::Closed ? now() : null;
    }

    private function notifyAssignee(Ticket $ticket, User $actor): void
    {
        $assignee = $ticket->assignee;

        if ($assignee && $assignee->isNot($actor)) {
            $assignee->notify(new TicketAssigned($ticket));
        }
    }
}
