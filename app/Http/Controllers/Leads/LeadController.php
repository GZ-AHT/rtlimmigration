<?php

namespace App\Http\Controllers\Leads;

use App\Actions\Leads\AssignLead;
use App\Actions\Leads\CaptureLead;
use App\Enums\ContactChannel;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\LeadRequest;
use App\Models\Country;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Service;
use App\Models\User;
use App\Support\CounselorDirectory;
use App\Support\Presenters\LeadPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lead management (REQ §7, §11.1). Mounted under /admin (Lead Manager,
 * Super Admin) and /counselor ("My Leads"); what each user sees is decided
 * by LeadPolicy and Lead::scopeVisibleTo, never by the URL.
 */
class LeadController extends Controller
{
    use ResolvesAreaRoutes;

    private const SORTABLE = ['created_at', 'name', 'next_follow_up_at', 'status'];

    public function index(Request $request): Response
    {
        return $this->listing($request, preset: []);
    }

    /** REQ §7 "All Contact Inquiries": leads submitted through the website form. */
    public function inquiries(Request $request): Response
    {
        return $this->listing($request, preset: ['source' => LeadSource::Website->value], title: 'Contact inquiries');
    }

    /** @param  array<string, string>  $preset */
    private function listing(Request $request, array $preset, string $title = 'Leads'): Response
    {
        Gate::authorize('viewAny', Lead::class);

        $user = $request->user();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tab' => ['nullable', Rule::in(['all', 'new', 'pending', 'closed'])],
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'country' => ['nullable', 'integer'],
            'service' => ['nullable', 'integer'],
            'counselor' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'follow_up' => ['nullable', Rule::in(['due', 'overdue'])],
            'duplicates' => ['nullable', Rule::in(['1'])],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]) + $preset;

        $base = Lead::query()->visibleTo($user)->when($preset['source'] ?? null, fn (Builder $q, string $s) => $q->where('source', $s));
        $tab = $filters['tab'] ?? 'all';

        $leads = (clone $base)
            ->with(['country:id,name', 'service:id,name', 'counselor:id,name'])
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $digits = preg_replace('/\D+/', '', $s) ?? '';
                $q->where(fn (Builder $q) => $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('lead_number', 'like', $like)
                    ->when(strlen($digits) >= 4, fn (Builder $q) => $q->orWhere('phone_normalized', 'like', "%{$digits}%")));
            })
            ->when($tab !== 'all', fn (Builder $q) => $this->applyTab($q, $tab))
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['country'] ?? null, fn (Builder $q, int|string $c) => $q->where('country_id', $c))
            ->when($filters['service'] ?? null, fn (Builder $q, int|string $s) => $q->where('service_id', $s))
            ->when($filters['source'] ?? null, fn (Builder $q, string $s) => $q->where('source', $s))
            ->when($filters['counselor'] ?? null, fn (Builder $q, string $c) => $c === 'unassigned' ? $q->whereNull('counselor_id') : $q->where('counselor_id', (int) $c))
            ->when(($filters['follow_up'] ?? null) === 'overdue', fn (Builder $q) => $q->where('next_follow_up_at', '<', now()))
            ->when(($filters['follow_up'] ?? null) === 'due', fn (Builder $q) => $q->where('next_follow_up_at', '<=', now()->endOfDay()))
            ->when($filters['duplicates'] ?? null, fn (Builder $q) => $q->whereNotNull('duplicate_of_id'))
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Lead $l) => [...LeadPresenter::row($l), 'url' => $this->areaRoute($request, 'leads.show', $l)]);

        $closed = array_map(fn ($s) => $s->value, array_filter(LeadStatus::cases(), fn ($s) => $s->isClosed()));

        return Inertia::render('leads/Index', [
            'title' => $title,
            'leads' => $leads,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'tab' => $tab,
                'status' => $filters['status'] ?? '',
                'country' => (string) ($filters['country'] ?? ''),
                'service' => (string) ($filters['service'] ?? ''),
                'counselor' => $filters['counselor'] ?? '',
                'source' => $preset === [] ? ($filters['source'] ?? '') : '',
                'follow_up' => $filters['follow_up'] ?? '',
                'duplicates' => $filters['duplicates'] ?? '',
                'sort' => $filters['sort'] ?? 'created_at',
                'direction' => $filters['direction'] ?? 'desc',
            ],
            'tabCounts' => [
                'all' => (clone $base)->count(),
                'new' => (clone $base)->where('status', LeadStatus::New->value)->count(),
                'pending' => (clone $base)->whereNotIn('status', [LeadStatus::New->value, ...$closed])->count(),
                'closed' => (clone $base)->whereIn('status', $closed)->count(),
            ],
            'options' => $this->options($user, includeSource: $preset === []),
            'canSeeAll' => $user->can('leads.assign'),
            'urls' => [
                'create' => $user->can('create', Lead::class) && $this->areaHas($request, 'leads.create') ? $this->areaRoute($request, 'leads.create') : null,
            ],
        ]);
    }

    /** @param  Builder<Lead>  $query */
    private function applyTab(Builder $query, string $tab): void
    {
        $closed = array_map(fn ($s) => $s->value, array_filter(LeadStatus::cases(), fn ($s) => $s->isClosed()));

        match ($tab) {
            'new' => $query->where('status', LeadStatus::New->value),
            // REQ §7 "Pending Leads": being worked, not yet closed.
            'pending' => $query->whereNotIn('status', [LeadStatus::New->value, ...$closed]),
            'closed' => $query->whereIn('status', $closed),
            default => null,
        };
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Lead::class);

        return Inertia::render('leads/Form', [
            'lead' => null,
            'options' => $this->options($request->user(), includeSource: true),
            'canAssign' => $request->user()->can('leads.assign'),
            'urls' => ['submit' => $this->areaRoute($request, 'leads.store'), 'cancel' => $this->areaRoute($request, 'leads.index')],
        ]);
    }

    public function store(LeadRequest $request, CaptureLead $capture, AssignLead $assign): RedirectResponse
    {
        Gate::authorize('create', Lead::class);

        $lead = $capture($request->validated(), LeadSource::from($request->validated('source')), $request->user());

        if ($request->filled('counselor_id') && $request->user()->can('leads.assign')) {
            $assign($lead, User::findOrFail($request->integer('counselor_id')), $request->user());
        }

        $this->toast([
            'type' => $lead->duplicate_of_id ? 'warning' : 'success',
            'message' => $lead->duplicate_of_id
                ? __('Lead :number created. It looks like a duplicate of an existing lead.', ['number' => $lead->lead_number])
                : __('Lead :number created.', ['number' => $lead->lead_number]),
        ]);

        return redirect($this->areaRoute($request, 'leads.show', $lead));
    }

    public function show(Request $request, Lead $lead): Response
    {
        Gate::authorize('view', $lead);

        $lead->load(['country', 'service', 'counselor', 'student', 'duplicateOf', 'creator', 'notes.user', 'contacts.user', 'assignments.fromCounselor', 'assignments.toCounselor', 'assignments.assigner', 'followUps.user']);
        $user = $request->user();

        return Inertia::render('leads/Show', [
            'lead' => LeadPresenter::detail($lead, $user),
            'timeline' => LeadPresenter::timeline($lead),
            'followUps' => array_map(fn (array $f) => [...$f, 'complete_url' => $this->areaRoute($request, 'leads.follow-ups.complete', [$lead, $f['id']])], LeadPresenter::openFollowUps($lead)),
            'counselors' => $user->can('assign', $lead) || $user->can('transfer', $lead) ? app(CounselorDirectory::class)->options() : [],
            'statuses' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], LeadStatus::manual()),
            'channels' => ContactChannel::options(),
            'urls' => [
                'index' => $this->areaRoute($request, 'leads.index'),
                'edit' => $this->areaRoute($request, 'leads.edit', $lead),
                'destroy' => $this->areaHas($request, 'leads.destroy') ? $this->areaRoute($request, 'leads.destroy', $lead) : null,
                'assign' => $this->areaHas($request, 'leads.assign') ? $this->areaRoute($request, 'leads.assign', $lead) : null,
                'status' => $this->areaRoute($request, 'leads.status', $lead),
                'notes' => $this->areaRoute($request, 'leads.notes.store', $lead),
                'contacts' => $this->areaRoute($request, 'leads.contacts.store', $lead),
                'followUps' => $this->areaRoute($request, 'leads.follow-ups.store', $lead),
                'convert' => $this->areaRoute($request, 'leads.convert', $lead),
                'duplicate' => $lead->duplicateOf ? $this->areaRoute($request, 'leads.show', $lead->duplicateOf) : null,
                'student' => $lead->student && $user->can('view', $lead->student) ? $this->areaRoute($request, 'students.show', $lead->student) : null,
            ],
        ]);
    }

    public function edit(Request $request, Lead $lead): Response
    {
        Gate::authorize('update', $lead);

        $lead->load(['country', 'service', 'counselor']);

        return Inertia::render('leads/Form', [
            'lead' => LeadPresenter::detail($lead, $request->user()),
            'options' => $this->options($request->user(), includeSource: true),
            'canAssign' => false,
            'urls' => ['submit' => $this->areaRoute($request, 'leads.update', $lead), 'cancel' => $this->areaRoute($request, 'leads.show', $lead)],
        ]);
    }

    public function update(LeadRequest $request, Lead $lead, CaptureLead $capture): RedirectResponse
    {
        Gate::authorize('update', $lead);
        abort_if($lead->status === LeadStatus::Converted, 403, __('Converted leads are read-only.'));

        $data = $request->validated();
        $email = Lead::normalizeEmail($data['email'] ?? null);
        $phone = Lead::normalizePhone($data['phone'] ?? null);

        $lead->fill([
            ...$data,
            'email_normalized' => $email,
            'phone_normalized' => $phone,
        ]);

        if ($lead->isDirty(['email_normalized', 'phone_normalized'])) {
            $lead->duplicate_of_id = $capture->findDuplicate($email, $phone, ignoreId: $lead->id)?->id;
        }

        $lead->save();

        $this->toast(['type' => 'success', 'message' => __('Lead updated.')]);

        return redirect($this->areaRoute($request, 'leads.show', $lead));
    }

    public function destroy(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('delete', $lead);

        $lead->delete();

        $this->toast(['type' => 'success', 'message' => __('Lead :number deleted.', ['number' => $lead->lead_number])]);

        return redirect($this->areaRoute($request, 'leads.index'));
    }

    /** @return array<string, mixed> */
    private function options(User $user, bool $includeSource): array
    {
        return [
            'statuses' => LeadStatus::options(),
            'countries' => Country::destinations()->get(['id', 'name'])->map(fn (Country $c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            'services' => Service::active()->get(['id', 'name'])->map(fn (Service $s) => ['value' => (string) $s->id, 'label' => $s->name])->values(),
            'channels' => ContactChannel::options(),
            // All sources for filtering; staff can't create "website" leads by hand.
            'sources' => $includeSource ? LeadSource::options() : [],
            'manualSources' => array_values(array_filter(LeadSource::options(), fn ($o) => $o['value'] !== LeadSource::Website->value)),
            'counselors' => $user->can('leads.assign') ? app(CounselorDirectory::class)->options() : [],
        ];
    }

    /** Lead pages a counselor works from: open follow-ups (REQ §8.1 "Follow-up Management"). */
    public function followUps(Request $request): Response
    {
        Gate::authorize('viewAny', Lead::class);

        $user = $request->user();
        $filter = $request->validate(['when' => ['nullable', Rule::in(['overdue', 'today', 'upcoming', 'all'])]])['when'] ?? 'all';

        $items = LeadFollowUp::query()
            ->whereNull('completed_at')
            ->whereHas('lead', fn (Builder $q) => $q->visibleTo($user))
            ->with(['lead:id,lead_number,name,status,counselor_id', 'user:id,name'])
            ->when($filter === 'overdue', fn (Builder $q) => $q->where('due_at', '<', now()))
            ->when($filter === 'today', fn (Builder $q) => $q->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]))
            ->when($filter === 'upcoming', fn (Builder $q) => $q->where('due_at', '>', now()->endOfDay()))
            ->orderBy('due_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (LeadFollowUp $f) => [
                'id' => $f->id,
                'due_at' => $f->due_at->toIso8601String(),
                'overdue' => $f->due_at->isPast(),
                'note' => $f->note,
                'owner' => $f->user?->name,
                'lead' => ['name' => $f->lead->name, 'number' => $f->lead->lead_number, 'status' => $f->lead->status->label(), 'tone' => $f->lead->status->tone()],
                'lead_url' => $this->areaRoute($request, 'leads.show', $f->lead_id),
                'complete_url' => $this->areaRoute($request, 'leads.follow-ups.complete', [$f->lead_id, $f->id]),
            ]);

        return Inertia::render('leads/FollowUps', [
            'followUps' => $items,
            'filter' => $filter,
            'canSeeAll' => $user->can('leads.assign'),
        ]);
    }
}
