<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Payments\PaymentLedger;
use App\Actions\Payments\RefundManager;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Payments\RefundController;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\Refund;
use App\Models\Student;
use App\Support\Gateways\GatewayManager;
use App\Support\Money;
use App\Support\Presenters\PaymentPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Student payments (REQ §10.1 "Payments", "Payment History", "Due
 * Payments"; §12.1 step 4). Own records only.
 */
class PortalPaymentController extends Controller
{
    public function index(Request $request, GatewayManager $gateways): Response
    {
        $student = $this->student($request);
        // REQ §10.1 "Due Payments": the same screen, showing only what is still owed.
        $dueOnly = $request->routeIs('portal.payments.due');
        $requests = $student->paymentRequests()->with(['type', 'file', 'payments.method'])->get();
        $gateway = $gateways->active();

        return Inertia::render('portal/Payments', [
            'dueOnly' => $dueOnly,
            'gateway' => $gateway ? [
                'label' => $gateway->label(),
                ...$gateway->checkout(),
                'endpoint' => $gateway->checkout()['mode'] === 'easy' ? route('portal.payments.checkout', absolute: false) : null,
                // Easy Checkout's script posts this back as "_token".
                'csrf' => $gateway->checkout()['mode'] === 'easy' ? csrf_token() : null,
            ] : null,
            'totals' => PaymentPresenter::totals($requests),
            'requests' => $requests
                ->when($dueOnly, fn ($all) => $all->filter(fn (PaymentRequest $r) => $r->status->isOpen())->sortBy(fn (PaymentRequest $r) => $r->due_date !== null ? $r->due_date->timestamp : PHP_INT_MAX))
                ->map(fn (PaymentRequest $r) => [
                    ...PaymentPresenter::request($r),
                    'invoice_url' => route('portal.payments.invoice', $r, false),
                    'online_url' => $gateway ? route('portal.payments.online', $r, false) : null,
                    // What can still be paid: outstanding minus payments already awaiting verification.
                    'outstanding_decimal' => Money::toDecimal(max(0, $r->outstanding() - (int) $r->payments->filter(fn (Payment $p) => $p->status->isAwaitingDecision())->sum('amount'))),
                    'awaiting' => $r->payments->filter(fn (Payment $p) => $p->status->isAwaitingDecision())->map(fn (Payment $p) => PaymentPresenter::payment($p))->values(),
                    'can_pay' => $r->status->isOpen() && $r->outstanding() - $r->payments->filter(fn (Payment $p) => $p->status->isAwaitingDecision())->sum('amount') > 0,
                    'pay_url' => route('portal.payments.store', $r, false),
                ])->values(),
            'methods' => PaymentMethod::manual()->where('student_selectable', true)->get()->map(fn (PaymentMethod $m) => [
                'value' => (string) $m->id, 'label' => $m->name, 'requires_reference' => $m->requires_reference, 'requires_evidence' => $m->requires_evidence, 'instructions' => $m->instructions,
            ])->values(),
            'evidence' => ['max_mb' => (int) floor(config('payments.evidence_max_kb') / 1024), 'extensions' => config('payments.evidence_mimes')],
        ]);
    }

    public function history(Request $request): Response
    {
        $student = $this->student($request);

        return Inertia::render('portal/PaymentHistory', [
            'payments' => $student->payments()->with(['request.type', 'method', 'confirmer'])->get()->map(fn (Payment $p) => [
                ...PaymentPresenter::payment($p),
                'evidence_url' => $p->evidence_path ? route('portal.payments.evidence', $p, false) : null,
                'receipt_url' => $p->receipt_number !== null ? route('portal.payments.receipt', $p, false) : null,
                'refundable_decimal' => Money::toDecimal($refundable = $p->refundable()),
                'refundable_label' => Money::format($refundable, $p->currency),
                'refund_url' => $refundable > 0 ? route('portal.refunds.store', $p, false) : null,
            ])->values(),
        ]);
    }

    /** REQ §10.1 "Refunds": refund requests and where each one stands. */
    public function refunds(Request $request): Response
    {
        $student = $this->student($request);

        return Inertia::render('portal/Refunds', [
            'refunds' => $student->refunds()->with(['request.type', 'payment', 'method'])->get()->map(fn (Refund $r) => PaymentPresenter::refund($r))->values(),
        ]);
    }

    /** A student asking for money back on one of their own confirmed payments. */
    public function requestRefund(Request $request, Payment $payment, RefundManager $refunds): RedirectResponse
    {
        abort_unless($payment->student_id === $this->student($request)->id, 403);
        Gate::authorize('create', [Refund::class, $payment]);

        $data = RefundController::validateRequest($request);
        $refunds->request($payment, Money::toMinor($data['amount']), $data['reason'], $request->user());

        $this->toast(['type' => 'success', 'message' => __('Your refund request has been sent. We will let you know the decision.')]);

        return redirect()->route('portal.refunds.index');
    }

    public function store(Request $request, PaymentRequest $paymentRequest, PaymentLedger $ledger): RedirectResponse
    {
        Gate::authorize('pay', $paymentRequest);
        abort_unless($paymentRequest->student_id === $this->student($request)->id, 403);

        $data = PaymentController::validatePayment($request);
        $method = PaymentMethod::findOrFail((int) $data['payment_method_id']);
        abort_unless($method->student_selectable, 422, __('That payment method is not available online.'));

        $ledger->submitPayment(
            $paymentRequest,
            $method,
            Money::toMinor($data['amount']),
            $data['transaction_reference'] ?? null,
            CarbonImmutable::parse($data['paid_on']),
            $request->file('evidence'),
            $request->user(),
            $data['notes'] ?? null,
        );

        $this->toast(['type' => 'success', 'message' => __('Thank you. Your payment has been sent to our Accounts team for verification.')]);

        return back();
    }

    /** REQ §10.1 "My Counselor": details and how to reach them. */
    public function counselor(Request $request): Response
    {
        $student = $this->student($request)->load('counselor.counselorProfile');
        $c = $student->counselor;

        return Inertia::render('portal/Counselor', [
            'counselor' => $c ? [
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->counselorProfile?->phone,
                'designation' => $c->counselorProfile?->designation,
                'bio' => $c->counselorProfile?->bio,
                'availability' => $c->counselorProfile?->availability->label(),
            ] : null,
        ]);
    }

    private function student(Request $request): Student
    {
        $student = $request->user()->student;
        abort_if($student === null, 403, __('No student record is linked to your account yet. Please contact your counselor.'));

        return $student;
    }
}
