<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\PaymentLedger;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Support\Money;
use App\Support\Presenters\PaymentPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Submitted payments: verification queue, confirmation, rejection and
 * history (REQ §9.1 "All Payments", "Pending Verification", "Payment
 * Confirmation", "Rejected Transactions"; §8.1 "Payment History").
 */
class PaymentController extends Controller
{
    use ResolvesAreaRoutes;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $user = $request->user();
        $preset = $request->route()?->defaults['status'] ?? null;
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'method' => ['nullable', 'integer'],
        ]);
        $status = $preset ?? ($filters['status'] ?? null);

        $base = Payment::query()->visibleTo($user);

        $payments = (clone $base)
            ->with(['request.type', 'student.user', 'method', 'submitter', 'confirmer'])
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where(fn (Builder $q) => $q->where('transaction_reference', 'like', $like)->orWhere('receipt_number', 'like', $like)
                    ->orWhereHas('request', fn (Builder $r) => $r->where('invoice_number', 'like', $like))
                    ->orWhereHas('student.user', fn (Builder $u) => $u->where('name', 'like', $like)));
            })
            ->when($status, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['method'] ?? null, fn (Builder $q, int|string $m) => $q->where('payment_method_id', $m))
            // Queues show the oldest first so nothing waits too long.
            ->when(in_array($status, ['pending', 'verified'], true), fn (Builder $q) => $q->oldest(), fn (Builder $q) => $q->latest())
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Payment $p) => [...PaymentPresenter::payment($p), 'url' => $this->areaRoute($request, 'payments.show', $p)]);

        $counts = (clone $base)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('payments/Payments', [
            'title' => match ($preset) {
                'pending' => 'Pending verification',
                'verified' => 'Payment confirmation',
                'rejected' => 'Rejected transactions',
                default => 'All payments',
            },
            'preset' => $preset,
            'payments' => $payments,
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? '', 'method' => (string) ($filters['method'] ?? '')],
            'counts' => collect(PaymentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'statuses' => PaymentStatus::options(),
            'methods' => PaymentMethod::active()->get(['id', 'name'])->map(fn ($m) => ['value' => (string) $m->id, 'label' => $m->name])->values(),
        ]);
    }

    public function show(Request $request, Payment $payment): Response
    {
        Gate::authorize('view', $payment);

        $payment->load(['request.type', 'request.student.user', 'request.requester', 'student.user', 'method', 'submitter', 'verifier', 'confirmer', 'statusHistory.changer']);
        $user = $request->user();
        $awaiting = $payment->status->isAwaitingDecision();
        $isRequester = $payment->request->requested_by === $user->id;

        return Inertia::render('payments/PaymentShow', [
            'payment' => [
                ...PaymentPresenter::payment($payment),
                'request' => [
                    ...PaymentPresenter::request($payment->request),
                    'url' => $this->areaRoute($request, 'payment-requests.show', $payment->request),
                ],
                'evidence_url' => $payment->evidence_path ? $this->areaRoute($request, 'payments.evidence', $payment) : null,
                'evidence_is_image' => str_starts_with((string) $payment->evidence_mime_type, 'image/'),
            ],
            'history' => $payment->statusHistory->map(fn ($h) => PaymentPresenter::history($h, 'payment'))->values(),
            'can' => [
                'verify' => $payment->status === PaymentStatus::Pending && $user->can('verify', $payment),
                'confirm' => $awaiting && $user->can('confirm', $payment) && ! $isRequester,
                'reject' => $awaiting && $user->can('confirm', $payment) && ! $isRequester,
            ],
            'isRequester' => $isRequester && $awaiting,
            'urls' => [
                'index' => $this->areaRoute($request, 'payments.index'),
                'verify' => $this->areaRoute($request, 'payments.verify', $payment),
                'confirm' => $this->areaRoute($request, 'payments.confirm', $payment),
                'reject' => $this->areaRoute($request, 'payments.reject', $payment),
            ],
        ]);
    }

    /** Proof of payment, served only after an authorization check. */
    public function evidence(Payment $payment): StreamedResponse
    {
        Gate::authorize('view', $payment);
        abort_if($payment->evidence_path === null, 404);

        $disk = Storage::disk((string) $payment->evidence_disk);
        abort_unless($disk->exists($payment->evidence_path), 404);

        $inline = in_array($payment->evidence_mime_type, config('documents.inline'), true);

        return $disk->response($payment->evidence_path, str_replace(['"', "\r", "\n"], '', (string) $payment->evidence_original_name), [
            'Content-Type' => (string) $payment->evidence_mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }

    /** Staff submitting a payment on the student's behalf (e.g. a deposit slip handed in). */
    public function store(Request $request, PaymentRequest $paymentRequest, PaymentLedger $ledger): RedirectResponse
    {
        Gate::authorize('pay', $paymentRequest);

        $data = self::validatePayment($request);
        $ledger->submitPayment(
            $paymentRequest,
            PaymentMethod::findOrFail((int) $data['payment_method_id']),
            Money::toMinor($data['amount']),
            $data['transaction_reference'] ?? null,
            CarbonImmutable::parse($data['paid_on']),
            $request->file('evidence'),
            $request->user(),
            $data['notes'] ?? null,
        );

        $this->toast(['type' => 'success', 'message' => __('Payment submitted for verification by Accounts.')]);

        return back();
    }

    /** REQ §12.3 cash / manual entry: Accounts records money received in person. */
    public function recordReceived(Request $request, PaymentRequest $paymentRequest, PaymentLedger $ledger): RedirectResponse
    {
        Gate::authorize('recordReceived', $paymentRequest);

        $data = self::validatePayment($request, withEvidence: false);
        $payment = $ledger->recordReceived(
            $paymentRequest,
            PaymentMethod::findOrFail((int) $data['payment_method_id']),
            Money::toMinor($data['amount']),
            $data['transaction_reference'] ?? null,
            CarbonImmutable::parse($data['paid_on']),
            $request->user(),
            $data['notes'] ?? null,
        );

        $this->toast(['type' => 'success', 'message' => __('Payment recorded and confirmed. Receipt :number.', ['number' => $payment->receipt_number])]);

        return back();
    }

    public function verify(Request $request, Payment $payment, PaymentLedger $ledger): RedirectResponse
    {
        Gate::authorize('verify', $payment);

        $ledger->verify($payment, $request->user());

        $this->toast(['type' => 'success', 'message' => __('Payment verified. It now awaits final confirmation.')]);

        return back();
    }

    public function confirm(Request $request, Payment $payment, PaymentLedger $ledger): RedirectResponse
    {
        Gate::authorize('confirm', $payment);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $payment = $ledger->confirm($payment, $request->user(), $data['note'] ?? null);

        $this->toast(['type' => 'success', 'message' => __('Payment confirmed. Receipt :number issued.', ['number' => $payment->receipt_number])]);

        return back();
    }

    public function reject(Request $request, Payment $payment, PaymentLedger $ledger): RedirectResponse
    {
        Gate::authorize('confirm', $payment);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $ledger->reject($payment, $request->user(), $data['reason']);

        $this->toast(['type' => 'success', 'message' => __('Payment rejected. The student and counselor have been told why.')]);

        return back();
    }

    /** @return array{payment_method_id: int|string, amount: string, transaction_reference?: string|null, paid_on: string, notes?: string|null} */
    public static function validatePayment(Request $request, bool $withEvidence = true): array
    {
        $rules = [
            'payment_method_id' => ['required', 'integer', Rule::exists(PaymentMethod::class, 'id')->where('is_active', true)],
            'amount' => ['required', 'string', 'regex:/^\d{1,11}(\.\d{1,2})?$/'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($withEvidence) {
            $rules['evidence'] = ['nullable', 'file', 'max:'.config('payments.evidence_max_kb'), 'mimes:'.implode(',', config('payments.evidence_mimes'))];
        }

        /** @var array{payment_method_id: int|string, amount: string, transaction_reference?: string|null, paid_on: string, notes?: string|null} */
        return $request->validate($rules, [
            'amount.regex' => __('Enter an amount like 5000 or 5000.50.'),
            'evidence.mimes' => __('Upload a PDF, JPG, PNG or WEBP file.'),
        ]);
    }
}
