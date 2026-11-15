<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\PaymentLedger;
use App\Enums\PaymentRequestStatus;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentFile;
use App\Support\Money;
use App\Support\Presenters\PaymentPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Payment requests / invoices (REQ §8.1 "Payment Requests", §9.1 "New
 * Payment Requests", "Student Invoices", §12.1). Admin, accounts and
 * counselor areas.
 */
class PaymentRequestController extends Controller
{
    use ResolvesAreaRoutes;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PaymentRequest::class);

        $user = $request->user();
        $preset = $request->route()?->defaults['status'] ?? null;
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([...array_column(PaymentRequestStatus::cases(), 'value'), 'open'])],
            'type' => ['nullable', 'integer'],
        ]);

        if ($preset !== null) {
            $filters['status'] = $preset;
        }

        $base = PaymentRequest::query()->visibleTo($user);

        $requests = (clone $base)
            ->with(['student.user', 'type', 'file', 'requester'])
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where(fn (Builder $q) => $q->where('invoice_number', 'like', $like)
                    ->orWhereHas('student', fn (Builder $st) => $st->where('student_number', 'like', $like)
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like))));
            })
            ->when(($filters['status'] ?? null) === 'open', fn (Builder $q) => $q->whereIn('status', $this->openStatuses()))
            ->when(($filters['status'] ?? 'open') !== 'open' && isset($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['type'] ?? null, fn (Builder $q, int|string $t) => $q->where('payment_type_id', $t))
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PaymentRequest $r) => [...PaymentPresenter::request($r), 'url' => $this->areaRoute($request, 'payment-requests.show', $r)]);

        return Inertia::render('payments/Requests', [
            'title' => $preset === 'unpaid' ? 'New payment requests' : 'Invoices',
            'preset' => $preset,
            'requests' => $requests,
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? '', 'type' => (string) ($filters['type'] ?? '')],
            'statuses' => [['value' => 'open', 'label' => 'Open (awaiting money)'], ...PaymentRequestStatus::options()],
            'types' => PaymentType::active()->get(['id', 'name'])->map(fn ($t) => ['value' => (string) $t->id, 'label' => $t->name])->values(),
            'totals' => [
                'outstanding' => Money::format((int) (clone $base)->whereIn('status', $this->openStatuses())->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as o')->value('o')),
                'open' => (clone $base)->whereIn('status', $this->openStatuses())->count(),
                'overdue' => (clone $base)->where('status', PaymentRequestStatus::Due->value)->count(),
            ],
            'urls' => [
                'create' => $user->can('payments.request') && $this->areaHas($request, 'payment-requests.create') ? $this->areaRoute($request, 'payment-requests.create') : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('payments.request'), 403);

        $student = $request->integer('student') ? Student::with(['user', 'files'])->find($request->integer('student')) : null;

        if ($student) {
            Gate::authorize('create', [PaymentRequest::class, $student]);
        }

        return Inertia::render('payments/RequestForm', [
            'student' => $student ? ['id' => $student->id, 'name' => $student->user->name, 'number' => $student->student_number] : null,
            'students' => $student ? [] : Student::query()->visibleTo($user)->with('user')->get()
                ->sortBy(fn (Student $s) => $s->user->name)
                ->map(fn (Student $s) => ['value' => (string) $s->id, 'label' => "{$s->user->name} · {$s->student_number}"])->values(),
            'files' => $student ? $student->files->map(fn (StudentFile $f) => ['value' => (string) $f->id, 'label' => $f->file_number])->values() : [],
            'presetFile' => $request->integer('file') ? (string) $request->integer('file') : '',
            'types' => PaymentType::active()->get()->map(fn (PaymentType $t) => [
                'value' => (string) $t->id,
                'label' => $t->name,
                'default_amount' => $t->default_amount !== null ? Money::toDecimal($t->default_amount) : null,
            ])->values(),
            'currency' => config('payments.currency'),
            'urls' => [
                'submit' => $this->areaRoute($request, 'payment-requests.store'),
                'cancel' => $student && $this->areaHas($request, 'students.show') ? $this->areaRoute($request, 'students.show', ['student' => $student, 'tab' => 'payments']) : $this->areaRoute($request, 'payment-requests.index'),
                'self' => $this->areaRoute($request, 'payment-requests.create'),
            ],
        ]);
    }

    public function store(Request $request, PaymentLedger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists(Student::class, 'id')],
            'payment_type_id' => ['required', 'integer', Rule::exists(PaymentType::class, 'id')->where('is_active', true)],
            'amount' => ['required', 'string', 'regex:/^\d{1,11}(\.\d{1,2})?$/'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'student_file_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['amount.regex' => __('Enter an amount like 5000 or 5000.50.')]);

        $student = Student::findOrFail((int) $data['student_id']);
        Gate::authorize('create', [PaymentRequest::class, $student]);

        $paymentRequest = $ledger->createRequest(
            $student,
            PaymentType::findOrFail((int) $data['payment_type_id']),
            Money::toMinor($data['amount']),
            isset($data['due_date']) ? CarbonImmutable::parse($data['due_date']) : null,
            $data['description'] ?? null,
            isset($data['student_file_id']) ? StudentFile::find((int) $data['student_file_id']) : null,
            $request->user(),
            $data['notes'] ?? null,
        );

        $this->toast(['type' => 'success', 'message' => __('Invoice :number raised. The student has been notified.', ['number' => $paymentRequest->invoice_number])]);

        return redirect($this->areaRoute($request, 'payment-requests.show', $paymentRequest));
    }

    public function show(Request $request, PaymentRequest $paymentRequest): Response
    {
        Gate::authorize('view', $paymentRequest);

        $paymentRequest->load(['student.user', 'student.counselor', 'type', 'file', 'requester', 'payments.method', 'payments.submitter', 'payments.verifier', 'payments.confirmer', 'statusHistory.changer']);
        $user = $request->user();

        return Inertia::render('payments/RequestShow', [
            'request' => [
                ...PaymentPresenter::request($paymentRequest),
                'notes' => $paymentRequest->notes,
                'cancel_reason' => $paymentRequest->cancel_reason,
                'counselor' => $paymentRequest->student->counselor?->name,
                'student_url' => $this->areaHas($request, 'students.show') && $user->can('view', $paymentRequest->student) ? $this->areaRoute($request, 'students.show', ['student' => $paymentRequest->student_id, 'tab' => 'payments']) : null,
            ],
            'payments' => $paymentRequest->payments->map(fn ($p) => [
                ...PaymentPresenter::payment($p),
                'url' => $this->areaHas($request, 'payments.show') ? $this->areaRoute($request, 'payments.show', $p) : null,
                'evidence_url' => $p->evidence_path && $this->areaHas($request, 'payments.evidence') ? $this->areaRoute($request, 'payments.evidence', $p) : null,
            ])->values(),
            'history' => $paymentRequest->statusHistory->map(fn ($h) => PaymentPresenter::history($h, 'request'))->values(),
            'methods' => PaymentMethod::active()->get()->map(fn (PaymentMethod $m) => [
                'value' => (string) $m->id, 'label' => $m->name, 'requires_reference' => $m->requires_reference, 'requires_evidence' => $m->requires_evidence, 'instructions' => $m->instructions,
            ])->values(),
            'can' => [
                'pay' => $paymentRequest->status->isOpen() && $user->can('pay', $paymentRequest),
                'record' => $paymentRequest->status->isOpen() && $user->can('recordReceived', $paymentRequest) && $paymentRequest->requested_by !== $user->id,
                'cancel' => $paymentRequest->status !== PaymentRequestStatus::Cancelled && $paymentRequest->amount_paid === 0 && $user->can('cancel', $paymentRequest),
            ],
            'outstandingDecimal' => Money::toDecimal($paymentRequest->outstanding()),
            'evidence' => ['max_mb' => (int) floor(config('payments.evidence_max_kb') / 1024), 'extensions' => config('payments.evidence_mimes')],
            'urls' => [
                'index' => $this->areaRoute($request, 'payment-requests.index'),
                'pay' => $this->areaRoute($request, 'payment-requests.payments.store', $paymentRequest),
                'record' => $this->areaHas($request, 'payment-requests.received') ? $this->areaRoute($request, 'payment-requests.received', $paymentRequest) : null,
                'cancel' => $this->areaRoute($request, 'payment-requests.cancel', $paymentRequest),
            ],
        ]);
    }

    public function cancel(Request $request, PaymentRequest $paymentRequest, PaymentLedger $ledger): RedirectResponse
    {
        Gate::authorize('cancel', $paymentRequest);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $ledger->cancelRequest($paymentRequest, $request->user(), $data['reason']);

        $this->toast(['type' => 'success', 'message' => __('Invoice cancelled.')]);

        return back();
    }

    /** @return list<string> */
    private function openStatuses(): array
    {
        return array_values(array_map(fn ($s) => $s->value, array_filter(PaymentRequestStatus::cases(), fn ($s) => $s->isOpen())));
    }
}
