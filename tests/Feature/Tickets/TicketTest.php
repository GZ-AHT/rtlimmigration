<?php

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketReplied;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

function newTicket($test, array $overrides = []): Ticket
{
    $test->actingAs($test->userWithRole(Role::SuperAdmin))->post(route('admin.tickets.store'), array_merge([
        'subject' => 'Visa interview date',
        'description' => 'When is my interview?',
        'priority' => 'high',
        'category_id' => TicketCategory::first()->id,
        'requester_name' => 'Visitor',
        'requester_email' => 'visitor@example.com',
    ], $overrides));

    return Ticket::latest('id')->firstOrFail();
}

it('creates a numbered ticket for an outside requester', function () {
    $ticket = newTicket($this);

    expect($ticket->ticket_number)->toStartWith('TKT-')
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->requesterEmail())->toBe('visitor@example.com');
});

it('notifies the assignee and only assigns ticket staff', function () {
    Notification::fake();
    $manager = $this->userWithRole(Role::LeadManager);

    newTicket($this, ['assigned_to' => $manager->id]);
    Notification::assertSentTo($manager, TicketAssigned::class);

    $this->actingAs($this->userWithRole(Role::SuperAdmin))
        ->post(route('admin.tickets.store'), ['subject' => 'S', 'description' => 'D', 'priority' => 'low', 'requester_name' => 'V', 'requester_email' => 'v@example.com', 'assigned_to' => $this->userWithRole(Role::Student)->id])
        ->assertSessionHasErrors('assigned_to');
});

it('emails replies to the requester and moves the ticket to pending', function () {
    Notification::fake();
    $ticket = newTicket($this);

    $this->post(route('admin.tickets.reply', $ticket), ['body' => 'Next Tuesday.', 'internal' => false])->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Pending)->and($ticket->replies()->first()->emailed_at)->not->toBeNull();
    Notification::assertSentTo(new AnonymousNotifiable, TicketReplied::class);
});

it('never emails internal notes', function () {
    Notification::fake();
    $ticket = newTicket($this);

    $this->post(route('admin.tickets.reply', $ticket), ['body' => 'Check passport first', 'internal' => true]);

    expect($ticket->replies()->first()->is_internal)->toBeTrue()->and($ticket->fresh()->status)->toBe(TicketStatus::Open);
    Notification::assertNothingSent();
});

it('stamps resolved and closed times', function () {
    $ticket = newTicket($this);

    $this->patch(route('admin.tickets.update', $ticket), ['status' => 'resolved']);
    expect($ticket->fresh()->resolved_at)->not->toBeNull();

    $this->patch(route('admin.tickets.update', $ticket), ['status' => 'closed']);
    expect($ticket->fresh()->closed_at)->not->toBeNull();

    $this->patch(route('admin.tickets.update', $ticket), ['status' => 'open']);
    expect($ticket->fresh()->closed_at)->toBeNull()->and($ticket->fresh()->resolved_at)->toBeNull();
});

it('filters by status and lets lead managers work tickets, not counselors', function () {
    newTicket($this);
    $closed = newTicket($this, ['subject' => 'Old']);
    $closed->update(['status' => TicketStatus::Closed]);

    $this->actingAs($this->userWithRole(Role::LeadManager))->get(route('admin.tickets.index', ['status' => 'open']))
        ->assertInertia(fn ($p) => $p->where('tickets.total', 1)->where('statusCounts.closed', 1));

    $this->actingAs($this->userWithRole(Role::Counselor))->get(route('admin.tickets.index'))->assertForbidden();
});

it('manages categories and keeps ones that have tickets', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);

    $this->actingAs($admin)->post(route('admin.ticket-categories.store'), ['name' => 'Scholarships', 'is_active' => true])->assertRedirect();
    $this->actingAs($admin)->post(route('admin.ticket-categories.store'), ['name' => 'Scholarships', 'is_active' => true])->assertSessionHasErrors('slug');

    $used = TicketCategory::first();
    newTicket($this, ['category_id' => $used->id]);
    $this->actingAs($admin)->delete(route('admin.ticket-categories.destroy', $used))->assertSessionHasErrors('category');

    $unused = TicketCategory::where('name', 'Scholarships')->first();
    $this->actingAs($admin)->delete(route('admin.ticket-categories.destroy', $unused))->assertRedirect();
    expect(TicketCategory::where('name', 'Scholarships')->exists())->toBeFalse();
});
