<?php

use App\Actions\Payments\PaymentLedger;
use App\Enums\PaymentRequestStatus;
use App\Enums\RefundStatus;
use App\Enums\Role;
use App\Models\Counselor;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\PaymentType;
use App\Models\Refund;
use App\Models\Student;
use App\Models\User;
use App\Notifications\RefundUpdated;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);

    $this->counselor = User::factory()->create()->assignRole(Role::Counselor->value);
    Counselor::create(['user_id' => $this->counselor->id]);
    $this->student = Student::factory()->create(['counselor_id' => $this->counselor->id]);
    $this->accounts = $this->userWithRole(Role::Accounts);
    $this->admin = $this->userWithRole(Role::SuperAdmin);
    $this->cash = PaymentMethod::where('slug', 'cash-at-office')->first();
    $this->bank = PaymentMethod::where('slug', 'bank-transfer-deposit')->first();
});

/** An invoice with one confirmed cash payment on it. */
function paidInvoice($test, int $invoice = 2000000, int $paid = 2000000): Payment
{
    $ledger = app(PaymentLedger::class);
    $request = $ledger->createRequest($test->student, PaymentType::where('slug', 'visa-processing-fee')->first(), $invoice, null, null, null, $test->counselor);

    return $ledger->recordReceived($request, $test->cash, $paid, null, CarbonImmutable::today(), $test->accounts, null);
}

/** Ask for a refund as the counselor and return it. */
function askRefund($test, Payment $payment, string $amount = '5000'): Refund
{
    $test->actingAs($test->counselor)
        ->post(route('counselor.payments.refunds.store', $payment), ['amount' => $amount, 'reason' => 'Student withdrew the application.'])
        ->assertSessionHasNoErrors();

    return Refund::latest('id')->firstOrFail();
}

describe('requesting a refund', function () {
    it('references the original payment, gets a number and marks the invoice', function () {
        Notification::fake();
        $payment = paidInvoice($this);

        $refund = askRefund($this, $payment);

        expect($refund->refund_number)->toStartWith('RFD-')
            ->and($refund->payment_id)->toBe($payment->id)
            ->and($refund->payment_request_id)->toBe($payment->payment_request_id)
            ->and($refund->amount)->toBe(500000)
            ->and($refund->status)->toBe(RefundStatus::Requested)
            ->and($refund->statusHistory()->count())->toBe(1)
            ->and($payment->request->fresh()->status)->toBe(PaymentRequestStatus::RefundRequested);

        // Nobody but Super Admin holds refunds.approve by default.
        Notification::assertSentTo($this->admin, RefundUpdated::class);
        Notification::assertNotSentTo($this->counselor, RefundUpdated::class);
    });

    it('cannot exceed what is still refundable, counting open requests', function () {
        $payment = paidInvoice($this);
        askRefund($this, $payment, '15000');

        $this->actingAs($this->counselor)
            ->post(route('counselor.payments.refunds.store', $payment), ['amount' => '5000.01', 'reason' => 'A second request.'])
            ->assertSessionHasErrors('amount');

        expect(Refund::count())->toBe(1)->and($payment->fresh()->refundable())->toBe(500000);
    });

    it('is refused for payments that are not confirmed', function () {
        $ledger = app(PaymentLedger::class);
        $request = $ledger->createRequest($this->student, PaymentType::first(), 100000, null, null, null, $this->counselor);
        $payment = $ledger->submitPayment($request, PaymentMethod::where('slug', 'bkash-nagad-rocket')->first(), 100000, 'TRX1', CarbonImmutable::today(), null, $this->student->user);

        $this->actingAs($this->counselor)
            ->post(route('counselor.payments.refunds.store', $payment), ['amount' => '100', 'reason' => 'Not confirmed yet.'])
            ->assertSessionHasErrors('amount');
    });

    it("stops counselors asking for refunds on other counselors' students", function () {
        $payment = paidInvoice($this);
        $other = User::factory()->create()->assignRole(Role::Counselor->value);

        $this->actingAs($other)
            ->post(route('counselor.payments.refunds.store', $payment), ['amount' => '100', 'reason' => 'Not my student.'])
            ->assertForbidden();
    });

    it('lets a student ask for a refund of their own payment only', function () {
        $payment = paidInvoice($this);
        $stranger = Student::factory()->create();

        $this->actingAs($stranger->user)
            ->post(route('portal.refunds.store', $payment), ['amount' => '100', 'reason' => 'Not my payment.'])
            ->assertForbidden();

        $this->actingAs($this->student->user)
            ->post(route('portal.refunds.store', $payment), ['amount' => '2500', 'reason' => 'I paid this fee twice.'])
            ->assertRedirect(route('portal.refunds.index'));

        expect(Refund::sole()->requested_by)->toBe($this->student->user_id);

        $this->actingAs($this->student->user)->get(route('portal.refunds.index'))
            ->assertInertia(fn ($page) => $page->component('portal/Refunds')->has('refunds', 1));
    });
});

describe('approving a refund', function () {
    it('needs the approve permission', function () {
        $refund = askRefund($this, paidInvoice($this));

        $this->actingAs($this->counselor)->patch(route('counselor.refunds.approve', $refund))->assertForbidden();
        $this->actingAs($this->accounts)->patch(route('accounts.refunds.approve', $refund))->assertForbidden();

        expect($refund->fresh()->status)->toBe(RefundStatus::Requested);
    });

    it('cannot be done by the person who requested it', function () {
        $payment = paidInvoice($this);
        $this->actingAs($this->admin)
            ->post(route('admin.payments.refunds.store', $payment), ['amount' => '100', 'reason' => 'Raised by the admin.'])
            ->assertSessionHasNoErrors();
        $refund = Refund::sole();

        $this->actingAs($this->admin)->patch(route('admin.refunds.approve', $refund))->assertSessionHasErrors('refund');

        expect($refund->fresh()->status)->toBe(RefundStatus::Requested);
    });

    it('records who approved and tells Accounts', function () {
        $refund = askRefund($this, paidInvoice($this));
        Notification::fake();

        $this->actingAs($this->admin)->patch(route('admin.refunds.approve', $refund), ['note' => 'Agreed with the client.'])->assertSessionHasNoErrors();

        $refund->refresh();
        expect($refund->status)->toBe(RefundStatus::Approved)
            ->and($refund->approved_by)->toBe($this->admin->id)
            ->and($refund->approved_at)->not->toBeNull()
            ->and($refund->statusHistory()->count())->toBe(2);

        Notification::assertSentTo($this->accounts, RefundUpdated::class, fn (RefundUpdated $n) => $n->event === 'approved');
        Notification::assertSentTo($this->student->user, RefundUpdated::class);
    });

    it('can be rejected with a reason, which reopens the invoice status', function () {
        $payment = paidInvoice($this);
        $refund = askRefund($this, $payment);

        $this->actingAs($this->admin)->patch(route('admin.refunds.reject', $refund), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->patch(route('admin.refunds.reject', $refund), ['reason' => 'Service already delivered.'])->assertSessionHasNoErrors();

        expect($refund->fresh()->status)->toBe(RefundStatus::Rejected)
            ->and($refund->fresh()->decision_note)->toBe('Service already delivered.')
            ->and($payment->request->fresh()->status)->toBe(PaymentRequestStatus::Paid)
            ->and($payment->fresh()->refundable())->toBe(2000000);
    });
});

describe('paying a refund out', function () {
    it('is only possible once approved', function () {
        $refund = askRefund($this, paidInvoice($this));

        $this->actingAs($this->accounts)
            ->patch(route('accounts.refunds.process', $refund), ['payment_method_id' => $this->cash->id])
            ->assertSessionHasErrors('refund');

        expect($refund->fresh()->status)->toBe(RefundStatus::Requested);
    });

    it('records the payout against the payment and the invoice', function () {
        $payment = paidInvoice($this);
        $refund = askRefund($this, $payment, '5000');
        $this->actingAs($this->admin)->patch(route('admin.refunds.approve', $refund));

        // Bank transfers need a reference.
        $this->actingAs($this->accounts)
            ->patch(route('accounts.refunds.process', $refund), ['payment_method_id' => $this->bank->id])
            ->assertSessionHasErrors('transaction_reference');

        $this->actingAs($this->accounts)
            ->patch(route('accounts.refunds.process', $refund), ['payment_method_id' => $this->bank->id, 'transaction_reference' => 'BANK-REF-9'])
            ->assertSessionHasNoErrors();

        $refund->refresh();
        expect($refund->status)->toBe(RefundStatus::Processed)
            ->and($refund->processed_by)->toBe($this->accounts->id)
            ->and($refund->transaction_reference)->toBe('BANK-REF-9')
            ->and($payment->fresh()->amount_refunded)->toBe(500000)
            ->and($payment->request->fresh()->amount_refunded)->toBe(500000)
            // Part refunded: the invoice is still a paid invoice.
            ->and($payment->request->fresh()->status)->toBe(PaymentRequestStatus::Paid)
            ->and($payment->fresh()->refundable())->toBe(1500000);
    });

    it('cannot be paid twice', function () {
        $payment = paidInvoice($this);
        $refund = askRefund($this, $payment, '5000');
        $this->actingAs($this->admin)->patch(route('admin.refunds.approve', $refund));
        $pay = fn () => $this->actingAs($this->accounts)->patch(route('accounts.refunds.process', $refund), ['payment_method_id' => $this->cash->id]);

        $pay()->assertSessionHasNoErrors();
        $pay()->assertSessionHasErrors('refund');

        expect($payment->fresh()->amount_refunded)->toBe(500000);
    });

    it('marks the invoice refunded once everything paid has gone back', function () {
        $payment = paidInvoice($this, 2000000, 2000000);
        $refund = askRefund($this, $payment, '20000');
        $this->actingAs($this->admin)->patch(route('admin.refunds.approve', $refund));
        $this->actingAs($this->accounts)->patch(route('accounts.refunds.process', $refund), ['payment_method_id' => $this->cash->id])->assertSessionHasNoErrors();

        $request = $payment->request->fresh();
        expect($request->status)->toBe(PaymentRequestStatus::Refunded)
            ->and($request->status->isOpen())->toBeFalse()
            ->and($payment->fresh()->refundable())->toBe(0);
    });

    it('is not available to counselors', function () {
        $refund = askRefund($this, paidInvoice($this));

        expect(Route::has('counselor.refunds.process'))->toBeFalse();
        $this->actingAs($this->counselor)->patch(route('accounts.refunds.process', $refund), ['payment_method_id' => $this->cash->id])->assertForbidden();
    });
});

describe('refund screens', function () {
    it('shows counselors only their own students', function () {
        askRefund($this, paidInvoice($this));
        $other = User::factory()->create()->assignRole(Role::Counselor->value);

        $this->actingAs($this->counselor)->get(route('counselor.refunds.index'))
            ->assertInertia(fn ($page) => $page->component('payments/Refunds')->has('refunds.data', 1));
        $this->actingAs($other)->get(route('counselor.refunds.index'))
            ->assertInertia(fn ($page) => $page->has('refunds.data', 0));
        $this->actingAs($other)->get(route('counselor.refunds.show', Refund::sole()))->assertForbidden();
    });

    it('lists approved refunds in the Accounts payout queue', function () {
        $first = askRefund($this, paidInvoice($this), '1000');
        askRefund($this, paidInvoice($this), '2000');
        $this->actingAs($this->admin)->patch(route('admin.refunds.approve', $first));

        $this->actingAs($this->accounts)->get(route('accounts.refunds.processing'))
            ->assertInertia(fn ($page) => $page->component('payments/Refunds')
                ->where('preset', 'approved')
                ->has('refunds.data', 1)
                ->where('refunds.data.0.refund_number', $first->refund_number));

        $this->actingAs($this->accounts)->get(route('accounts.refunds.show', $first))
            ->assertInertia(fn ($page) => $page->component('payments/RefundShow')
                ->where('can.process', true)
                ->where('can.approve', false));
    });

    it('offers the refund action on a confirmed payment', function () {
        $payment = paidInvoice($this);

        $this->actingAs($this->counselor)->get(route('counselor.payments.show', $payment))
            ->assertInertia(fn ($page) => $page->component('payments/PaymentShow')
                ->where('can.refund', true)
                ->where('refundable.minor', 2000000));
    });
});

it('keeps a part-paid invoice on the due list while a refund is in progress', function () {
    $payment = paidInvoice($this, 2000000, 500000);
    expect($payment->request->fresh()->status)->toBe(PaymentRequestStatus::Partial);

    // The balance is still owed whatever happens to the refund.
    $refund = askRefund($this, $payment, '1000');
    $request = PaymentRequest::find($payment->payment_request_id);
    expect($request->status)->toBe(PaymentRequestStatus::Partial)->and($request->status->isOpen())->toBeTrue();

    $this->actingAs($this->admin)->patch(route('admin.refunds.reject', $refund), ['reason' => 'No grounds.']);
    expect(PaymentRequest::find($payment->payment_request_id)->status)->toBe(PaymentRequestStatus::Partial);
});
