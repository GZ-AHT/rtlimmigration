<?php

namespace App\Http\Controllers\Applications;

use App\Actions\Applications\ApplicationWorkflow;
use App\Actions\Payments\PaymentLedger;
use App\Enums\ApplicationStatus;
use App\Enums\StageKind;
use App\Enums\VisaKind;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Country;
use App\Models\Document;
use App\Models\PaymentRequest;
use App\Models\Student;
use App\Models\StudentFile;
use App\Models\VisaType;
use App\Models\WorkflowStage;
use App\Support\Money;
use App\Support\Presenters\ApplicationPresenter;
use App\Support\Presenters\PaymentPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Applications of every visa type (REQ §13, §14, §17; roadmap Q10, Q14).
 * Admin and counselor areas. The Working Visa and Work & Examination
 * menus are this same screen filtered to one family of visa types.
 */
class ApplicationController extends Controller
{
    use ResolvesAreaRoutes;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Application::class);

        $user = $request->user();
        $kind = VisaKind::tryFrom((string) ($request->route()?->defaults['kind'] ?? ''));
        // "Application Status Update": the cases still being worked on.
        $activeOnly = (bool) ($request->route()?->defaults['active'] ?? false);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'integer'],
            'country' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
        ]);

        $base = Application::query()
            ->visibleTo($user)
            ->when($kind, fn (Builder $q) => $q->whereHas('visaType', fn (Builder $t) => $t->where('kind', $kind)))
            ->when($activeOnly, fn (Builder $q) => $q->where('status', ApplicationStatus::InProgress->value));

        $applications = (clone $base)
            ->with(['student.user', 'visaType', 'country', 'file', 'stage'])
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where(fn (Builder $q) => $q->where('application_number', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('organisation', 'like', $like)
                    ->orWhere('reference_number', 'like', $like)
                    ->orWhereHas('student', fn (Builder $st) => $st->where('student_number', 'like', $like)
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like))));
            })
            ->when($filters['type'] ?? null, fn (Builder $q, int|string $t) => $q->where('visa_type_id', $t))
            ->when($filters['country'] ?? null, fn (Builder $q, int|string $c) => $q->where('country_id', $c))
            ->when(! $activeOnly ? ($filters['status'] ?? null) : null, fn (Builder $q, string $s) => $q->where('status', $s))
            // The status-update list is worked from the longest-untouched case.
            ->when($activeOnly, fn (Builder $q) => $q->oldest('updated_at'), fn (Builder $q) => $q->latest())
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Application $a) => [...ApplicationPresenter::row($a), 'url' => $this->areaRoute($request, 'applications.show', $a)]);

        $counts = (clone $base)->selectRaw('status, count(*) as c')->groupBy('status')->toBase()->pluck('c', 'status');

        return Inertia::render('applications/Index', [
            'title' => match (true) {
                $activeOnly => 'Application status update',
                $kind === VisaKind::Work => 'Working visa',
                $kind === VisaKind::Exam => 'Work & examination',
                default => 'Visa processing',
            },
            'kind' => $kind?->value,
            'activeOnly' => $activeOnly,
            'applications' => $applications,
            'filters' => ['search' => $filters['search'] ?? '', 'type' => (string) ($filters['type'] ?? ''), 'country' => (string) ($filters['country'] ?? ''), 'status' => $filters['status'] ?? ''],
            'types' => VisaType::active()->when($kind, fn (Builder $q) => $q->where('kind', $kind))->get(['id', 'name'])->map(fn (VisaType $t) => ['value' => (string) $t->id, 'label' => $t->name])->values(),
            'countries' => Country::destinations()->get(['id', 'name'])->map(fn (Country $c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            'statuses' => ApplicationStatus::options(),
            // REQ §17 "Visa Reports": how the listed applications stand.
            'totals' => collect(ApplicationStatus::cases())->map(fn (ApplicationStatus $s) => ['key' => $s->value, 'label' => $s->label(), 'tone' => $s->tone(), 'count' => (int) ($counts[$s->value] ?? 0)])->values(),
            'urls' => [
                'create' => $user->can('applications.update') ? $this->areaRoute($request, 'applications.create', array_filter(['kind' => $kind?->value])) : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('applications.update'), 403);

        $kind = VisaKind::tryFrom((string) $request->query('kind'));
        $student = $request->integer('student') ? Student::with(['user', 'files'])->find($request->integer('student')) : null;

        if ($student) {
            Gate::authorize('create', [Application::class, $student]);
        }

        return Inertia::render('applications/Form', [
            'student' => $student ? ['id' => $student->id, 'name' => $student->user->name, 'number' => $student->student_number] : null,
            'students' => $student ? [] : Student::query()->visibleTo($user)->with('user')->get()
                ->sortBy(fn (Student $s) => $s->user->name)
                ->map(fn (Student $s) => ['value' => (string) $s->id, 'label' => "{$s->user->name} · {$s->student_number}"])->values(),
            'files' => $student ? $student->files->map(fn (StudentFile $f) => ['value' => (string) $f->id, 'label' => $f->file_number])->values() : [],
            'presetFile' => $request->integer('file') ? (string) $request->integer('file') : '',
            'types' => VisaType::active()->get()->map(fn (VisaType $t) => [
                'value' => (string) $t->id,
                'label' => $t->name,
                'kind' => $t->kind->value,
                'description' => $t->description,
                'labels' => ['title' => $t->kind->titleLabel(), 'organisation' => $t->kind->organisationLabel(), 'schedule' => $t->kind->scheduleLabel()],
            ])->values(),
            'presetKind' => $kind?->value,
            'countries' => Country::destinations()->get(['id', 'name'])->map(fn (Country $c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            'urls' => [
                'submit' => $this->areaRoute($request, 'applications.store'),
                'cancel' => $this->areaRoute($request, 'applications.index'),
                'self' => $this->areaRoute($request, 'applications.create'),
            ],
        ]);
    }

    public function store(Request $request, ApplicationWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists(Student::class, 'id')],
            'visa_type_id' => ['required', 'integer', Rule::exists(VisaType::class, 'id')->where('is_active', true)],
            'country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
            'student_file_id' => ['nullable', 'integer'],
            ...self::detailRules(),
        ]);

        $student = Student::findOrFail((int) $data['student_id']);
        Gate::authorize('create', [Application::class, $student]);

        $application = $workflow->start(
            $student,
            VisaType::findOrFail((int) $data['visa_type_id']),
            isset($data['country_id']) ? Country::find((int) $data['country_id']) : null,
            isset($data['student_file_id']) ? StudentFile::find((int) $data['student_file_id']) : null,
            $data,
            $request->user(),
        );

        $this->toast(['type' => 'success', 'message' => __('Application :number started. The student has been notified.', ['number' => $application->application_number])]);

        return redirect($this->areaRoute($request, 'applications.show', $application));
    }

    public function show(Request $request, Application $application): Response
    {
        Gate::authorize('view', $application);

        $application->load([
            'student.user', 'student.counselor', 'visaType.documentCategories', 'visaType.requirements', 'visaType.paymentType',
            'country', 'file', 'workflow.stages', 'stage', 'creator', 'history.changer', 'history.documents',
        ]);
        $user = $request->user();
        $active = $application->status->isActive();
        $canUpdate = $active && $user->can('update', $application);
        $type = $application->visaType;
        $invoices = $application->student_file_id
            ? PaymentRequest::with(['type', 'file'])->where('student_file_id', $application->student_file_id)->latest('id')->get()
            : collect();

        return Inertia::render('applications/Show', [
            'application' => [
                ...ApplicationPresenter::row($application),
                'notes' => $application->notes,
                'counselor' => $application->student->counselor?->name,
                'created_by' => $application->creator?->name,
                'workflow' => $application->workflow->name,
                'progress' => ApplicationPresenter::progress($application),
                'scheduled_local' => $application->scheduled_at?->format('Y-m-d\TH:i'),
                'student_url' => $this->areaHas($request, 'students.show') && $user->can('view', $application->student) ? $this->areaRoute($request, 'students.show', $application->student_id) : null,
                'file_url' => $application->student_file_id && $this->areaHas($request, 'files.show') ? $this->areaRoute($request, 'files.show', $application->student_file_id) : null,
            ],
            'stages' => ApplicationPresenter::stages($application),
            'history' => $application->history->map(fn ($h) => ApplicationPresenter::history($h, fn (Document $d) => $this->areaRoute($request, 'documents.show', $d)))->values(),
            'checklist' => ApplicationPresenter::checklist($application),
            'requirements' => ApplicationPresenter::requirements($application),
            'invoices' => $invoices->map(fn (PaymentRequest $r) => [
                ...PaymentPresenter::request($r),
                'url' => $this->areaHas($request, 'payment-requests.show') && $user->can('view', $r) ? $this->areaRoute($request, 'payment-requests.show', $r) : null,
            ])->values(),
            'fee' => $type->paymentType && $type->fee_amount ? ['label' => Money::format($type->fee_amount), 'type' => $type->paymentType->name] : null,
            'can' => [
                'update' => $canUpdate,
                'decide' => $active && $user->can('decide', $application),
                'withdraw' => $canUpdate,
                'raiseFee' => $canUpdate && $user->can('payments.request') && $type->paymentType !== null && (bool) $type->fee_amount,
            ],
            'upload' => ['max_mb' => (int) floor(config('documents.max_size_kb') / 1024), 'extensions' => array_keys(config('documents.allowed'))],
            'urls' => [
                'index' => $this->areaRoute($request, 'applications.index'),
                'advance' => $this->areaRoute($request, 'applications.advance', $application),
                'update' => $this->areaRoute($request, 'applications.update', $application),
                'withdraw' => $this->areaRoute($request, 'applications.withdraw', $application),
                'fee' => $this->areaRoute($request, 'applications.fee', $application),
            ],
        ]);
    }

    /** Correct the descriptive details; never the stage or the decision. */
    public function update(Request $request, Application $application): RedirectResponse
    {
        Gate::authorize('update', $application);
        abort_unless($application->status->isActive(), 403);

        $application->update($request->validate([...self::detailRules(), 'result' => ['nullable', 'string', 'max:255']]));

        $this->toast(['type' => 'success', 'message' => __('Application details saved.')]);

        return back();
    }

    /** REQ §13 "Application Status Update", §13.1–13.3. */
    public function advance(Request $request, Application $application, ApplicationWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('update', $application);

        $allowed = config('documents.allowed');
        $data = $request->validate([
            'stage_id' => ['required', 'integer', Rule::exists(WorkflowStage::class, 'id')->where('workflow_id', $application->workflow_id)->whereNull('deleted_at')],
            'note' => ['nullable', 'string', 'max:2000'],
            'decision' => ['nullable', Rule::in(['approved', 'rejected'])],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
            'office_visit_at' => ['nullable', 'date', 'after_or_equal:today'],
            'result' => ['nullable', 'string', 'max:255'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', 'max:'.config('documents.max_size_kb'), 'mimes:'.implode(',', array_keys($allowed)), 'mimetypes:'.implode(',', array_unique(array_merge(...array_values($allowed))))],
        ], [
            'files.*.mimes' => __('Upload PDF, JPG, PNG, WEBP, DOC or DOCX files.'),
            'files.*.mimetypes' => __('Upload PDF, JPG, PNG, WEBP, DOC or DOCX files.'),
        ]);

        $stage = WorkflowStage::findOrFail((int) $data['stage_id']);

        if ($stage->kind === StageKind::Decision && $stage->id !== $application->current_stage_id) {
            Gate::authorize('decide', $application);
        }

        $history = $workflow->advance($application, $stage, $data, array_values($request->file('files', [])), $request->user());

        $this->toast(['type' => 'success', 'message' => $history->student_notified_at
            ? __('Updated to ":stage". The student has been notified.', ['stage' => $stage->name])
            : __('Updated to ":stage".', ['stage' => $stage->name])]);

        return back();
    }

    public function withdraw(Request $request, Application $application, ApplicationWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('update', $application);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $workflow->withdraw($application, $data['reason'], $request->user());

        $this->toast(['type' => 'success', 'message' => __('Application withdrawn.')]);

        return back();
    }

    /** REQ §17 "Visa Fees": raise this visa type's fee as an invoice. */
    public function fee(Request $request, Application $application, ApplicationWorkflow $workflow, PaymentLedger $ledger): RedirectResponse
    {
        Gate::authorize('update', $application);
        Gate::authorize('create', [PaymentRequest::class, $application->student]);

        $invoice = $workflow->raiseFee($application->load('visaType.paymentType', 'student.user', 'file'), $ledger, $request->user());

        $this->toast(['type' => 'success', 'message' => __('Invoice :number raised. The student has been notified.', ['number' => $invoice->invoice_number])]);

        return back();
    }

    /** @return array<string, list<mixed>> */
    private static function detailRules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'organisation' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
