<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Payments\PaymentLedger;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Payments\PaymentController;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\Student;
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
    public function index(Request $request): Response
    {
        $student = $this->student($request);
        $requests = $student->paymentRequests()->with(['type', 'file', 'payments.method'])->get();

        return Inertia::render('portal/Payments', [
            'totals' => PaymentPresenter::totals($requests),
            'requests' => $requests->map(fn (PaymentRequest $r) => [
                ...PaymentPresenter::request($r),
                // What can still be paid: outstanding minus payments already awaiting verification.
                'outstanding_decimal' => Money::toDecimal(max(0, $r->outstanding() - (int) $r->payments->filter(fn (Payment $p) => $p->status->isAwaitingDecision())->sum('amount'))),
                'awaiting' => $r->payments->filter(fn (Payment $p) => $p->status->isAwaitingDecision())->map(fn (Payment $p) => PaymentPresenter::payment($p))->values(),
                'can_pay' => $r->status->isOpen() && $r->outstanding() - $r->payments->filter(fn (Payment $p) => $p->status->isAwaitingDecision())->sum('amount') > 0,
                'pay_url' => route('portal.payments.store', $r, false),
            ])->values(),
            'methods' => PaymentMethod::active()->where('student_selectable', true)->get()->map(fn (PaymentMethod $m) => [
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
            ])->values(),
        ]);
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
