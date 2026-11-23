<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tickets\ManageTicket;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Support\Presenters\ActivityPresenter;
use App\Support\Presenters\TicketPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/** REQ §7 support tickets. */
class TicketController extends Controller
{
    private const SORTABLE = ['created_at', 'updated_at', 'priority', 'last_reply_at'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Ticket::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'category' => ['nullable', 'integer'],
            'assignee' => ['nullable', 'string', 'max:20'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $filters['sort'] ?? 'updated_at';
        $direction = $filters['direction'] ?? 'desc';

        $tickets = Ticket::query()
            ->with(['category:id,name', 'assignee:id,name', 'requester:id,name,email'])
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where(fn (Builder $q) => $q->where('subject', 'like', $like)->orWhere('ticket_number', 'like', $like)
                    ->orWhere('requester_email', 'like', $like)->orWhere('requester_name', 'like', $like)
                    ->orWhereHas('requester', fn (Builder $u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['priority'] ?? null, fn (Builder $q, string $p) => $q->where('priority', $p))
            ->when($filters['category'] ?? null, fn (Builder $q, int|string $c) => $q->where('category_id', $c))
            ->when($filters['assignee'] ?? null, fn (Builder $q, string $a) => match ($a) {
                'unassigned' => $q->whereNull('assigned_to'),
                'me' => $q->where('assigned_to', $request->user()->id),
                default => $q->where('assigned_to', (int) $a),
            })
            // Priority is stored as text, so sort it by severity explicitly.
            ->when($sort === 'priority', fn (Builder $q) => $q->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low') ".($direction === 'asc' ? 'desc' : 'asc')))
            ->when($sort !== 'priority', fn (Builder $q) => $q->orderBy($sort, $direction))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Ticket $t) => TicketPresenter::row($t));

        $counts = Ticket::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('admin/tickets/Index', [
            'tickets' => $tickets,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'priority' => $filters['priority'] ?? '',
                'category' => (string) ($filters['category'] ?? ''),
                'assignee' => $filters['assignee'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusCounts' => collect(TicketStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'options' => $this->options(),
            'can' => ['create' => $request->user()->can('create', Ticket::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Ticket::class);

        $requester = $request->integer('requester') ? User::find($request->integer('requester')) : null;

        return Inertia::render('admin/tickets/Form', [
            'options' => $this->options(),
            'requester' => $requester ? ['id' => $requester->id, 'name' => $requester->name, 'email' => $requester->email] : null,
            'canAssign' => $request->user()->can('tickets.assign'),
        ]);
    }

    public function store(Request $request, ManageTicket $manage): RedirectResponse
    {
        Gate::authorize('create', Ticket::class);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:10000'],
            'category_id' => ['nullable', 'integer', Rule::exists(TicketCategory::class, 'id')],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'requester_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'requester_name' => ['nullable', 'required_without:requester_id', 'string', 'max:120'],
            'requester_email' => ['nullable', 'required_without:requester_id', 'email', 'max:255'],
            'assigned_to' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
        ]);

        if (! $request->user()->can('tickets.assign')) {
            unset($data['assigned_to']);
        }

        $this->guardAssignee($data['assigned_to'] ?? null);

        $ticket = $manage->create($data, $request->user());

        $this->toast(['type' => 'success', 'message' => __('Ticket :number created.', ['number' => $ticket->ticket_number])]);

        return to_route('admin.tickets.show', $ticket);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['category', 'assignee', 'requester', 'replies.user']);

        return Inertia::render('admin/tickets/Show', [
            'ticket' => TicketPresenter::detail($ticket),
            'history' => Activity::query()->whereMorphedTo('subject', $ticket)->with(['causer:id,name'])->latest()->limit(20)->get()
                ->map(fn (Activity $a) => ActivityPresenter::present($a)),
            'options' => $this->options(),
            'can' => [
                'update' => $request->user()->can('update', $ticket),
                'assign' => $request->user()->can('assign', $ticket),
                'delete' => $request->user()->can('delete', $ticket),
            ],
        ]);
    }

    public function update(Request $request, Ticket $ticket, ManageTicket $manage): RedirectResponse
    {
        Gate::authorize('update', $ticket);

        $data = $request->validate([
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists(TicketCategory::class, 'id')],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'assigned_to' => ['sometimes', 'nullable', 'integer', Rule::exists(User::class, 'id')],
        ]);

        if (array_key_exists('assigned_to', $data)) {
            Gate::authorize('assign', $ticket);
            $this->guardAssignee($data['assigned_to']);
        }

        $manage->update($ticket, $data, $request->user());

        $this->toast(['type' => 'success', 'message' => __('Ticket updated.')]);

        return back();
    }

    public function destroy(Ticket $ticket): RedirectResponse
    {
        Gate::authorize('delete', $ticket);

        $ticket->delete();

        $this->toast(['type' => 'success', 'message' => __('Ticket :number deleted.', ['number' => $ticket->ticket_number])]);

        return to_route('admin.tickets.index');
    }

    public function reply(Request $request, Ticket $ticket, ManageTicket $manage): RedirectResponse
    {
        Gate::authorize('update', $ticket);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'internal' => ['required', 'boolean'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
        ]);

        $reply = $manage->reply($ticket, $request->user(), $data['body'], (bool) $data['internal'], isset($data['status']) ? TicketStatus::from($data['status']) : null);

        $this->toast(['type' => 'success', 'message' => $reply->is_internal
            ? __('Internal note added.')
            : ($reply->emailed_at ? __('Reply sent to :email.', ['email' => $ticket->requesterEmail()]) : __('Reply added.'))]);

        return back();
    }

    /** Tickets can only be assigned to staff who can work tickets. */
    private function guardAssignee(?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        $user = User::find($userId);

        if (! $user || ! $user->isActive() || ! $user->can('tickets.manage')) {
            throw ValidationException::withMessages(['assigned_to' => __('Choose an active staff member who can handle tickets.')]);
        }
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'statuses' => TicketStatus::options(),
            'priorities' => TicketPriority::options(),
            'categories' => TicketCategory::active()->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            'assignees' => User::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->filter(fn (User $u) => $u->can('tickets.manage'))
                ->map(fn (User $u) => ['value' => (string) $u->id, 'label' => $u->name])
                ->values(),
        ];
    }
}
