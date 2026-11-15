<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Counselors\SaveCounselor;
use App\Concerns\ProfileValidationRules;
use App\Enums\CounselorAvailability;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Counselor;
use App\Models\Lead;
use App\Models\Student;
use App\Models\User;
use App\Support\Presenters\ActivityPresenter;
use App\Support\Presenters\LeadPresenter;
use App\Support\Presenters\StudentPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/** REQ §8 Counselor Management. */
class CounselorController extends Controller
{
    use ProfileValidationRules;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Counselor::class);

        // Users given the counselor role from the Users screen get a profile too.
        User::role(Role::Counselor->value)->doesntHave('counselorProfile')->each(fn (User $u) => SaveCounselor::ensureProfile($u));

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'availability' => ['nullable', Rule::enum(CounselorAvailability::class)],
        ]);

        $closed = $this->closedStatuses();

        $counselors = Counselor::query()
            ->whereHas('user', fn (Builder $q) => $q->role(Role::Counselor->value))
            ->with('user')
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->whereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($filters['availability'] ?? null, fn (Builder $q, string $a) => $q->where('availability', $a))
            ->get()
            ->sortBy(fn (Counselor $c) => $c->user->name)
            ->values();

        $ids = $counselors->pluck('user_id');
        $open = Lead::whereIn('counselor_id', $ids)->whereNotIn('status', $closed)->selectRaw('counselor_id, count(*) as c')->groupBy('counselor_id')->pluck('c', 'counselor_id');
        $converted = Lead::whereIn('counselor_id', $ids)->where('status', LeadStatus::Converted->value)->selectRaw('counselor_id, count(*) as c')->groupBy('counselor_id')->pluck('c', 'counselor_id');
        $students = Student::whereIn('counselor_id', $ids)->selectRaw('counselor_id, count(*) as c')->groupBy('counselor_id')->pluck('c', 'counselor_id');

        return Inertia::render('admin/counselors/Index', [
            'counselors' => $counselors->map(fn (Counselor $c) => [
                'id' => $c->id,
                'name' => $c->user->name,
                'email' => $c->user->email,
                'designation' => $c->designation,
                'availability' => $c->availability->value,
                'availability_label' => $c->availability->label(),
                'availability_tone' => $c->availability->tone(),
                'account_active' => $c->user->isActive(),
                'open_leads' => (int) ($open[$c->user_id] ?? 0),
                'capacity' => $c->max_active_leads,
                'converted' => (int) ($converted[$c->user_id] ?? 0),
                'students' => (int) ($students[$c->user_id] ?? 0),
            ]),
            'filters' => ['search' => $filters['search'] ?? '', 'availability' => $filters['availability'] ?? ''],
            'availabilities' => CounselorAvailability::options(),
            'can' => ['create' => $request->user()->can('create', Counselor::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Counselor::class);

        return Inertia::render('admin/counselors/Form', ['counselor' => null, 'availabilities' => CounselorAvailability::options()]);
    }

    public function store(Request $request, SaveCounselor $save): RedirectResponse
    {
        Gate::authorize('create', Counselor::class);

        $counselor = $save->create($this->validated($request), $request->user());

        $this->toast(['type' => 'success', 'message' => __('Counselor added. An invitation email has been sent.')]);

        return to_route('admin.counselors.show', $counselor);
    }

    public function show(Request $request, Counselor $counselor): Response
    {
        Gate::authorize('view', $counselor);

        $counselor->load('user');
        $userId = $counselor->user_id;
        $closed = $this->closedStatuses();

        $total = Lead::where('counselor_id', $userId)->count();
        $converted = Lead::where('counselor_id', $userId)->where('status', LeadStatus::Converted->value)->count();
        $contacted = Lead::where('counselor_id', $userId)->whereNotNull('last_contacted_at')->count();

        return Inertia::render('admin/counselors/Show', [
            'counselor' => [
                'id' => $counselor->id,
                'user_id' => $userId,
                'name' => $counselor->user->name,
                'email' => $counselor->user->email,
                'phone' => $counselor->phone,
                'designation' => $counselor->designation,
                'bio' => $counselor->bio,
                'availability' => $counselor->availability->value,
                'availability_label' => $counselor->availability->label(),
                'availability_tone' => $counselor->availability->tone(),
                'max_active_leads' => $counselor->max_active_leads,
                'account_active' => $counselor->user->isActive(),
                'last_login_at' => $counselor->user->last_login_at?->toIso8601String(),
                'can' => ['update' => $request->user()->can('update', $counselor), 'view_user' => $request->user()->can('view', $counselor->user)],
            ],
            // REQ §8 "Counselor Performance".
            'performance' => [
                ['key' => 'open', 'label' => 'Open leads', 'value' => Lead::where('counselor_id', $userId)->whereNotIn('status', $closed)->count(), 'icon' => 'person_search'],
                ['key' => 'contacted', 'label' => 'Leads contacted', 'value' => $contacted, 'icon' => 'call'],
                ['key' => 'converted', 'label' => 'Converted', 'value' => $converted, 'icon' => 'task_alt', 'hint' => $total ? round($converted / $total * 100).'% conversion rate' : null],
                ['key' => 'students', 'label' => 'Assigned students', 'value' => Student::where('counselor_id', $userId)->count(), 'icon' => 'school'],
            ],
            'leads' => Lead::where('counselor_id', $userId)->whereNotIn('status', $closed)->with(['country:id,name', 'service:id,name'])->latest()->limit(10)->get()
                ->map(fn (Lead $l) => [...LeadPresenter::row($l), 'url' => route('admin.leads.show', $l, false)]),
            'students' => Student::where('counselor_id', $userId)->with('user')->latest()->limit(10)->get()
                ->map(fn (Student $s) => [...StudentPresenter::row($s), 'url' => route('admin.students.show', $s, false)]),
            // REQ §8 "Counselor Activity Logs".
            'activity' => Activity::query()->whereMorphedTo('causer', $counselor->user)->with(['causer:id,name', 'subject'])->latest()->limit(15)->get()
                ->map(fn (Activity $a) => ActivityPresenter::present($a)),
        ]);
    }

    public function edit(Counselor $counselor): Response
    {
        Gate::authorize('update', $counselor);

        $counselor->load('user');

        return Inertia::render('admin/counselors/Form', [
            'counselor' => [
                'id' => $counselor->id,
                'name' => $counselor->user->name,
                'email' => $counselor->user->email,
                'phone' => $counselor->phone,
                'designation' => $counselor->designation,
                'availability' => $counselor->availability->value,
                'max_active_leads' => $counselor->max_active_leads,
                'bio' => $counselor->bio,
            ],
            'availabilities' => CounselorAvailability::options(),
        ]);
    }

    public function update(Request $request, Counselor $counselor, SaveCounselor $save): RedirectResponse
    {
        Gate::authorize('update', $counselor);

        // Editing a Super Admin's details goes through the Users screen.
        Gate::authorize('update', $counselor->user);

        $save->update($counselor, $this->validated($request, $counselor->user_id));

        $this->toast(['type' => 'success', 'message' => __('Counselor updated.')]);

        return to_route('admin.counselors.show', $counselor);
    }

    /**
     * @return array{name: string, email: string, phone?: string|null, designation?: string|null, availability: string, max_active_leads?: int|null, bio?: string|null, send_invitation?: bool}
     */
    private function validated(Request $request, ?int $userId = null): array
    {
        /** @var array{name: string, email: string, phone?: string|null, designation?: string|null, availability: string, max_active_leads?: int|null, bio?: string|null, send_invitation?: bool} */
        return $request->validate([
            ...$this->profileRules($userId),
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+()\-\s]{6,32}$/'],
            'designation' => ['nullable', 'string', 'max:100'],
            'availability' => ['required', Rule::enum(CounselorAvailability::class)],
            'max_active_leads' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'send_invitation' => ['sometimes', 'boolean'],
        ]);
    }

    /** @return list<string> */
    private function closedStatuses(): array
    {
        return array_values(array_map(fn ($s) => $s->value, array_filter(LeadStatus::cases(), fn ($s) => $s->isClosed())));
    }
}
