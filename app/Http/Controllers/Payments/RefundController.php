<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\RefundManager;
use App\Enums\RefundStatus;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Support\Money;
use App\Support\Presenters\PaymentPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Refunds (REQ §8.1 "Refund Requests", §9.1 "Refund Requests" and "Refund
 * Processing", §12.5 rule 8). Admin, accounts and counselor areas.
 */
class RefundController extends Controller
{
    use ResolvesAreaRoutes;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Refund::class);

        $user = $request->user();
        $preset = $request->route()?->defaults['status'] ?? null;
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(RefundStatus::class)],
        ]);
        $status = $preset ?? ($filters['status'] ?? null);

        $base = Refund::query()->visibleTo($user);

        $refunds = (clone $base)
            ->with(['request.type', 'payment', 'student.user', 'requester', 'approver', 'processor', 'method'])
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where(fn (Builder $q) => $q->where('refund_number', 'like', $like)
                    ->orWhereHas('request', fn (Builder $r) => $r->where('invoice_number', 'like', $like))
                    ->orWhereHas('student', fn (Builder $st) => $st->where('student_number', 'like', $like)
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like))));
            })
            ->when($status, fn (Builder $q, string $s) => $q->where('status', $s))
            // The payout queue shows the longest-waiting first.
            ->when($preset === 'approved', fn (Builder $q) => $q->oldest('approved_at'), fn (Builder $q) => $q->latest())
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Refund $r) => [...PaymentPresenter::refund($r), 'url' => $this->areaRoute($request, 'refunds.show', $r)]);

        $counts = (clone $base)->selectRaw('status, count(*) as c, sum(amount) as total')->groupBy('status')->get()->keyBy(fn (Refund $r) => $r->status->value);

        return Inertia::render('payments/Refunds', [
            'title' => $preset === 'approved' ? 'Refund processing' : 'Refund requests',
            'preset' => $preset,
            'refunds' => $refunds,
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''],
            'statuses' => RefundStatus::options(),
            'totals' => collect(RefundStatus::cases())->map(fn (RefundStatus $s) => [
                'key' => $s->value,
                'label' => $s->label(),
                'count' => (int) ($counts[$s->value]->c ?? 0),
                'total' => Money::format((int) ($counts[$s->value]->total ?? 0)),
            ])->values(),
        ]);
    }

    public function show(Request $request, Refund $refund): Response
    {
        Gate::authorize('view', $refund);

        $refund->load(['request.type', 'payment.method', 'student.user', 'student.counselor', 'requester', 'approver', 'processor', 'method', 'statusHistory.changer']);
        $user = $request->user();
        $isRequester = $refund->requested_by === $user->id;

        return Inertia::render('payments/RefundShow', [
            'refund' => [
                ...PaymentPresenter::refund($refund),
                'counselor' => $refund->student->counselor?->name,
                'payment' => [
                    'amount_label' => Money::format($refund->payment->amount, $refund->payment->currency),
                    'refunded_label' => Money::format($refund->payment->amount_refunded, $refund->payment->currency),
                    'method' => $refund->payment->method->name,
                    'transaction_reference' => $refund->payment->transaction_reference,
                    'paid_on' => $refund->payment->paid_on->toDateString(),
                    'url' => $this->areaHas($request, 'payments.show') ? $this->areaRoute($request, 'payments.show', $refund->payment_id) : null,
                ],
                'invoice_url' => $this->areaRoute($request, 'payment-requests.show', $refund->payment_request_id),
            ],
            'history' => $refund->statusHistory->map(fn ($h) => PaymentPresenter::history($h, 'refund'))->values(),
            'methods' => PaymentMethod::manual()->get()->map(fn (PaymentMethod $m) => ['value' => (string) $m->id, 'label' => $m->name, 'requires_reference' => $m->requires_reference])->values(),
            'can' => [
                'approve' => $refund->status === RefundStatus::Requested && $user->can('approve', $refund) && ! $isRequester,
                'reject' => $refund->status->isOpen() && $user->can('approve', $refund),
                'process' => $refund->status === RefundStatus::Approved && $user->can('process', $refund) && $this->areaHas($request, 'refunds.process'),
            ],
            'isRequester' => $isRequester && $refund->status === RefundStatus::Requested && $user->can('approve', $refund),
            'urls' => [
                'index' => $this->areaRoute($request, 'refunds.index'),
                'approve' => $this->areaRoute($request, 'refunds.approve', $refund),
                'reject' => $this->areaRoute($request, 'refunds.reject', $refund),
                'process' => $this->areaHas($request, 'refunds.process') ? $this->areaRoute($request, 'refunds.process', $refund) : null,
            ],
        ]);
    }

    /** Ask for a refund of one confirmed payment. */
    public function store(Request $request, Payment $payment, RefundManager $refunds): RedirectResponse
    {
        Gate::authorize('create', [Refund::class, $payment]);

        $data = self::validateRequest($request);
        $refund = $refunds->request($payment, Money::toMinor($data['amount']), $data['reason'], $request->user());

        $this->toast(['type' => 'success', 'message' => __('Refund :number requested. It now awaits approval.', ['number' => $refund->refund_number])]);

        return redirect($this->areaRoute($request, 'refunds.show', $refund));
    }

    public function approve(Request $request, Refund $refund, RefundManager $refunds): RedirectResponse
    {
        Gate::authorize('approve', $refund);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $refunds->approve($refund, $request->user(), $data['note'] ?? null);

        $this->toast(['type' => 'success', 'message' => __('Refund approved. Accounts can now pay it out.')]);

        return back();
    }

    public function reject(Request $request, Refund $refund, RefundManager $refunds): RedirectResponse
    {
        Gate::authorize('approve', $refund);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $refunds->reject($refund, $request->user(), $data['reason']);

        $this->toast(['type' => 'success', 'message' => __('Refund rejected. The student and counselor have been told why.')]);

        return back();
    }

    public function process(Request $request, Refund $refund, RefundManager $refunds): RedirectResponse
    {
        Gate::authorize('process', $refund);

        $data = $request->validate([
            'payment_method_id' => ['required', 'integer', Rule::exists(PaymentMethod::class, 'id')->where('is_active', true)],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $refunds->process($refund, $request->user(), PaymentMethod::findOrFail((int) $data['payment_method_id']), $data['transaction_reference'] ?? null, $data['notes'] ?? null);

        $this->toast(['type' => 'success', 'message' => __('Refund recorded as paid. The student has been notified.')]);

        return back();
    }

    /** @return array{amount: string, reason: string} */
    public static function validateRequest(Request $request): array
    {
        /** @var array{amount: string, reason: string} */
        return $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d{1,11}(\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], ['amount.regex' => __('Enter an amount like 5000 or 5000.50.')]);
    }
}
