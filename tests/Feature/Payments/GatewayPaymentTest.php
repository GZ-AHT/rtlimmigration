<?php

use App\Actions\Payments\PaymentLedger;
use App\Enums\GatewayTransactionStatus;
use App\Enums\PaymentRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Counselor;
use App\Models\GatewayTransaction;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\PaymentDecided;
use App\Notifications\PaymentSubmitted;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    config([
        'payments.gateway' => 'sslcommerz',
        'payments.gateways.sslcommerz.store_id' => 'teststore',
        'payments.gateways.sslcommerz.store_password' => 'secret-pass',
        'payments.gateways.sslcommerz.sandbox' => true,
    ]);
    // No real call may reach SSLCommerz. Inertia's own SSR ping to the Vite dev server is not ours to fake.
    Http::preventStrayRequests();
    Http::allowStrayRequests(['http://*:5173/*']);

    $this->counselor = User::factory()->create()->assignRole(Role::Counselor->value);
    Counselor::create(['user_id' => $this->counselor->id]);
    $this->student = Student::factory()->create(['counselor_id' => $this->counselor->id]);
    $this->accounts = $this->userWithRole(Role::Accounts);
    $this->invoice = app(PaymentLedger::class)->createRequest($this->student, PaymentType::where('slug', 'visa-processing-fee')->first(), 2000000, null, null, null, $this->counselor);
});

/** Start an online payment as the student and return the attempt. */
function startOnline($test): GatewayTransaction
{
    Http::fake(['sandbox.sslcommerz.com/gwprocess/*' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/EasyCheckOut/abc', 'sessionkey' => 'SESSION123'])]);

    $test->actingAs($test->student->user)
        ->post(route('portal.payments.online', $test->invoice), [], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://sandbox.sslcommerz.com/EasyCheckOut/abc');

    return GatewayTransaction::latest('id')->firstOrFail();
}

/** What SSLCommerz's validation API says about the attempt. */
/**
 * SSLCommerz's transaction lookup (merchantTransIDvalidationAPI), which
 * verify() consults before accepting a fail or cancel claim. With no
 * elements it says the attempt was never paid.
 *
 * @param  list<array<string, mixed>>  $elements
 */
function fakeLookup(array $elements = []): void
{
    Http::fake(['sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php*' => Http::response(['APIConnect' => 'DONE', 'no_of_trans_found' => count($elements), 'element' => $elements])]);
}

function fakeValidation(GatewayTransaction $transaction, array $overrides = []): void
{
    Http::fake(['sandbox.sslcommerz.com/validator/*' => Http::response(array_merge([
        'status' => 'VALID',
        'tran_id' => $transaction->tran_id,
        'val_id' => 'VAL123',
        'amount' => '20000.00',
        'currency' => 'BDT',
        'currency_type' => 'BDT',
        'currency_amount' => '20000.00',
        'bank_tran_id' => 'BANK987',
        'card_type' => 'BKASH-BKash',
        'risk_level' => '0',
    ], $overrides))]);
}

describe('starting an online payment', function () {
    it('opens a gateway session for the outstanding amount and sends the student there', function () {
        $transaction = startOnline($this);

        expect($transaction->amount)->toBe(2000000)
            ->and($transaction->status)->toBe(GatewayTransactionStatus::Initiated)
            ->and($transaction->session_key)->toBe('SESSION123')
            ->and(strlen($transaction->tran_id))->toBeLessThanOrEqual(30)
            // Opening a session moves no money.
            ->and(Payment::count())->toBe(0)
            ->and($this->invoice->fresh()->status)->toBe(PaymentRequestStatus::Unpaid);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'gwprocess/v4/api.php')
            && $r['store_id'] === 'teststore'
            && $r['total_amount'] === '20000.00'
            && $r['tran_id'] === $transaction->tran_id
            && str_contains($r['ipn_url'], '/payments/gateway/sslcommerz/ipn'));
    });

    it('answers the Easy Checkout script with the gateway URL in the shape it expects', function () {
        Http::fake(['sandbox.sslcommerz.com/gwprocess/*' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/EasyCheckOut/abc', 'sessionkey' => 'S1', 'storeLogo' => 'https://sandbox.sslcommerz.com/logo.png'])]);

        // The script posts form fields: order, cart_json and the CSRF token as _token.
        $this->actingAs($this->student->user)
            ->post(route('portal.payments.checkout'), ['order' => 'invoice', 'cart_json' => json_encode(['invoice' => $this->invoice->id])])
            ->assertOk()
            ->assertExactJson(['status' => 'success', 'data' => 'https://sandbox.sslcommerz.com/EasyCheckOut/abc', 'logo' => 'https://sandbox.sslcommerz.com/logo.png']);

        expect(GatewayTransaction::sole()->amount)->toBe(2000000)->and(Payment::count())->toBe(0);

        $this->actingAs($this->student->user)->get(route('portal.payments.index'))
            ->assertInertia(fn ($page) => $page
                ->where('gateway.mode', 'easy')
                ->where('gateway.script', 'https://sandbox.sslcommerz.com/embed.min.js')
                ->where('gateway.endpoint', route('portal.payments.checkout', absolute: false)));
    });

    it('tells the Easy Checkout script when a session cannot be opened', function () {
        Http::fake(['sandbox.sslcommerz.com/gwprocess/*' => Http::response(['status' => 'FAILED', 'failedreason' => 'Store Credential Error'])]);

        $cart = ['cart_json' => json_encode(['invoice' => $this->invoice->id])];

        $this->actingAs($this->student->user)->post(route('portal.payments.checkout'), $cart)
            ->assertOk()->assertJson(['status' => 'fail', 'data' => null]);

        // Someone else's invoice, a missing one and a malformed payload are all refused.
        $stranger = Student::factory()->create();
        $this->actingAs($stranger->user)->post(route('portal.payments.checkout'), $cart)->assertForbidden();
        $this->actingAs($this->student->user)->post(route('portal.payments.checkout'), ['cart_json' => json_encode(['invoice' => 999999])])->assertForbidden();
        $this->actingAs($this->student->user)->post(route('portal.payments.checkout'), ['cart_json' => 'not json'])->assertForbidden();
    });

    it('can be switched back to the hosted page', function () {
        config(['payments.gateways.sslcommerz.checkout' => 'hosted']);

        $this->actingAs($this->student->user)->get(route('portal.payments.index'))
            ->assertInertia(fn ($page) => $page->where('gateway.mode', 'hosted')->where('gateway.script', null)->where('gateway.endpoint', null)->where('gateway.csrf', null));
    });

    it("refuses another student's invoice", function () {
        $stranger = Student::factory()->create();

        $this->actingAs($stranger->user)->post(route('portal.payments.online', $this->invoice))->assertForbidden();
        expect(GatewayTransaction::count())->toBe(0);
    });

    it('is switched off until the gateway is configured', function () {
        config(['payments.gateways.sslcommerz.store_id' => null]);

        $this->actingAs($this->student->user)->post(route('portal.payments.online', $this->invoice))->assertSessionHasErrors('gateway');
        $this->actingAs($this->student->user)->get(route('portal.payments.index'))
            ->assertInertia(fn ($page) => $page->where('gateway', null)->where('requests.0.online_url', null));
    });

    it('reports a gateway outage without leaving a live attempt', function () {
        Http::fake(['sandbox.sslcommerz.com/gwprocess/*' => Http::response(['status' => 'FAILED', 'failedreason' => 'Store Credential Error'])]);

        $this->actingAs($this->student->user)->post(route('portal.payments.online', $this->invoice))->assertSessionHasErrors('gateway');

        expect(GatewayTransaction::sole()->status)->toBe(GatewayTransactionStatus::Failed);
    });

    it('never sends the store password to the browser', function () {
        $this->actingAs($this->student->user)->get(route('portal.payments.index'))
            ->assertOk()
            ->assertDontSee('secret-pass');
    });
});

describe('gateway callbacks', function () {
    it('confirms the payment only after validating it with the gateway', function () {
        Notification::fake();
        $transaction = startOnline($this);
        fakeValidation($transaction);

        $this->post(route('gateway.ipn', 'sslcommerz'), ['tran_id' => $transaction->tran_id, 'val_id' => 'VAL123', 'status' => 'VALID', 'amount' => '20000.00'])
            ->assertOk();

        $payment = Payment::sole();
        expect($payment->status)->toBe(PaymentStatus::Confirmed)
            ->and($payment->amount)->toBe(2000000)
            ->and($payment->receipt_number)->toStartWith('RCP-')
            ->and($payment->confirmed_by)->toBeNull()
            ->and($payment->transaction_reference)->toBe('BANK987')
            ->and($payment->method->slug)->toBe('online-payment-sslcommerz')
            ->and($this->invoice->fresh()->status)->toBe(PaymentRequestStatus::Paid)
            ->and($this->invoice->fresh()->amount_paid)->toBe(2000000)
            ->and($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Paid)
            ->and($transaction->fresh()->payment_id)->toBe($payment->id);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'validationserverAPI.php') && $r['val_id'] === 'VAL123' && $r['store_passwd'] === 'secret-pass');
        Notification::assertSentTo($this->student->user, PaymentDecided::class);
        Notification::assertSentTo($this->counselor, PaymentDecided::class);
    });

    it('does not mark anything paid because the browser returned to the success page', function () {
        $transaction = startOnline($this);
        // The payer's browser claims success, but the gateway does not agree.
        fakeValidation($transaction, ['status' => 'INVALID_TRANSACTION']);

        $this->post(route('gateway.return', ['gateway' => 'sslcommerz', 'outcome' => 'success']), ['tran_id' => $transaction->tran_id, 'val_id' => 'FORGED', 'status' => 'VALID'])
            ->assertOk()
            ->assertSee('Payment not completed');

        expect(Payment::count())->toBe(0)
            ->and($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Invalid)
            ->and($this->invoice->fresh()->amount_paid)->toBe(0)
            ->and($this->invoice->fresh()->status)->toBe(PaymentRequestStatus::Failed);
    });

    it('does not trust a success callback that carries no validation ID', function () {
        $transaction = startOnline($this);
        // It is checked with the gateway, which knows of no validated payment.
        Http::fake(['sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php*' => Http::response(['APIConnect' => 'DONE', 'element' => []])]);

        $this->post(route('gateway.return', ['gateway' => 'sslcommerz', 'outcome' => 'success']), ['tran_id' => $transaction->tran_id, 'status' => 'VALID'])->assertOk();

        expect(Payment::count())->toBe(0)->and($this->invoice->fresh()->amount_paid)->toBe(0);
    });

    it('rejects a validated amount that does not match the attempt', function () {
        $transaction = startOnline($this);
        fakeValidation($transaction, ['amount' => '10.00', 'currency_amount' => '10.00']);

        $this->post(route('gateway.ipn', 'sslcommerz'), ['tran_id' => $transaction->tran_id, 'val_id' => 'VAL123', 'status' => 'VALID']);

        expect(Payment::count())->toBe(0)->and($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Invalid);
    });

    it('rejects a validation that belongs to a different transaction', function () {
        $transaction = startOnline($this);
        fakeValidation($transaction, ['tran_id' => 'SOMEONE-ELSES']);

        $this->post(route('gateway.ipn', 'sslcommerz'), ['tran_id' => $transaction->tran_id, 'val_id' => 'VAL123', 'status' => 'VALID']);

        expect(Payment::count())->toBe(0)->and($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Invalid);
    });

    it('processes a callback once however many times it is delivered', function () {
        $transaction = startOnline($this);
        fakeValidation($transaction);
        $payload = ['tran_id' => $transaction->tran_id, 'val_id' => 'VAL123', 'status' => 'VALID'];

        $this->post(route('gateway.ipn', 'sslcommerz'), $payload)->assertOk();
        $this->post(route('gateway.ipn', 'sslcommerz'), $payload)->assertOk();
        // The browser return for the same payment arrives as well.
        $this->post(route('gateway.return', ['gateway' => 'sslcommerz', 'outcome' => 'success']), $payload)->assertOk()->assertSee('Payment received');

        expect(Payment::count())->toBe(1)
            ->and($this->invoice->fresh()->amount_paid)->toBe(2000000)
            ->and(WebhookEvent::sole()->deliveries)->toBe(3);
    });

    it('holds a payment the gateway flags as risky for Accounts to check', function () {
        Notification::fake();
        $transaction = startOnline($this);
        fakeValidation($transaction, ['risk_level' => '1', 'risk_title' => 'Suspicious card']);

        $this->post(route('gateway.ipn', 'sslcommerz'), ['tran_id' => $transaction->tran_id, 'val_id' => 'VAL123', 'status' => 'VALID']);

        $payment = Payment::sole();
        expect($payment->status)->toBe(PaymentStatus::Pending)
            ->and($payment->receipt_number)->toBeNull()
            ->and($this->invoice->fresh()->amount_paid)->toBe(0)
            ->and($this->invoice->fresh()->status)->toBe(PaymentRequestStatus::Pending)
            ->and($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Review);

        Notification::assertSentTo($this->accounts, PaymentSubmitted::class);

        // Accounts can then confirm it like any submitted payment.
        $this->actingAs($this->accounts)->patch(route('accounts.payments.confirm', $payment))->assertSessionHasNoErrors();
        expect($this->invoice->fresh()->status)->toBe(PaymentRequestStatus::Paid);
    });

    it('records a failed attempt and lets a later valid callback still settle it', function () {
        $transaction = startOnline($this);
        fakeLookup();

        $this->post(route('gateway.return', ['gateway' => 'sslcommerz', 'outcome' => 'fail']), ['tran_id' => $transaction->tran_id, 'status' => 'FAILED', 'error' => 'Insufficient balance'])
            ->assertOk()->assertSee('Payment not completed');

        expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Failed)
            ->and($transaction->fresh()->failure_reason)->toBe('Insufficient balance')
            ->and($this->invoice->fresh()->status)->toBe(PaymentRequestStatus::Failed)
            // A failed attempt does not close the invoice.
            ->and($this->invoice->fresh()->status->isOpen())->toBeTrue();

        fakeValidation($transaction);
        $this->post(route('gateway.ipn', 'sslcommerz'), ['tran_id' => $transaction->tran_id, 'val_id' => 'VAL123', 'status' => 'VALID']);

        expect($this->invoice->fresh()->status)->toBe(PaymentRequestStatus::Paid);
    });

    it('notes a cancelled attempt without touching the invoice', function () {
        $transaction = startOnline($this);
        fakeLookup();

        $this->post(route('gateway.return', ['gateway' => 'sslcommerz', 'outcome' => 'cancel']), ['tran_id' => $transaction->tran_id, 'status' => 'CANCELLED'])
            ->assertOk()->assertSee('Payment cancelled');

        expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Cancelled)
            ->and($this->invoice->fresh()->status)->toBe(PaymentRequestStatus::Unpaid);
    });

    it('settles a cancel or fail claim that SSLCommerz shows was actually paid', function () {
        $transaction = startOnline($this);
        fakeLookup([['status' => 'VALID', 'tran_id' => $transaction->tran_id, 'val_id' => 'VAL888', 'amount' => '20000.00', 'currency' => 'BDT', 'currency_type' => 'BDT', 'currency_amount' => '20000.00', 'bank_tran_id' => 'BANK888', 'risk_level' => '0']]);

        // e.g. the Easy Checkout popup closed after the payer had paid.
        $this->post(route('gateway.return', ['gateway' => 'sslcommerz', 'outcome' => 'cancel']), ['tran_id' => $transaction->tran_id, 'status' => 'CANCELLED'])
            ->assertOk()->assertSee('Payment received');

        expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Paid)
            ->and(Payment::sole()->transaction_reference)->toBe('BANK888')
            ->and($this->invoice->fresh()->status)->toBe(PaymentRequestStatus::Paid);
    });

    it('still records a cancellation when the lookup cannot reach SSLCommerz', function () {
        $transaction = startOnline($this);
        Http::fake(['sandbox.sslcommerz.com/validator/*' => Http::response('Bad gateway', 502)]);

        $this->post(route('gateway.return', ['gateway' => 'sslcommerz', 'outcome' => 'cancel']), ['tran_id' => $transaction->tran_id, 'status' => 'CANCELLED'])
            ->assertOk()->assertSee('Payment cancelled');

        expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Cancelled)->and(Payment::count())->toBe(0);
    });

    it('changes nothing when the gateway cannot be reached to validate', function () {
        $transaction = startOnline($this);
        Http::fake(['sandbox.sslcommerz.com/validator/*' => Http::response('Bad gateway', 502)]);

        $this->post(route('gateway.return', ['gateway' => 'sslcommerz', 'outcome' => 'success']), ['tran_id' => $transaction->tran_id, 'val_id' => 'VAL123', 'status' => 'VALID'])
            ->assertOk()->assertSee('Confirming your payment');

        expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Initiated)->and(Payment::count())->toBe(0);
    });

    it('settles a return that names only our transaction by asking the gateway about it', function () {
        $transaction = startOnline($this);
        Http::fake(['sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php*' => Http::response(['APIConnect' => 'DONE', 'no_of_trans_found' => 2, 'element' => [
            ['status' => 'FAILED', 'tran_id' => $transaction->tran_id],
            ['status' => 'VALID', 'tran_id' => $transaction->tran_id, 'val_id' => 'VAL777', 'amount' => '20000.00', 'currency' => 'BDT', 'currency_type' => 'BDT', 'currency_amount' => '20000.00', 'bank_tran_id' => 'BANK777', 'risk_level' => '0'],
        ]])]);

        // Easy Checkout brings the page back with a plain GET.
        $this->get(route('gateway.return', ['gateway' => 'sslcommerz', 'outcome' => 'success', 't' => $transaction->tran_id]))
            ->assertOk()->assertSee('Payment received');

        expect(Payment::sole()->transaction_reference)->toBe('BANK777')
            ->and($transaction->fresh()->validation_id)->toBe('VAL777')
            ->and($this->invoice->fresh()->status)->toBe(PaymentRequestStatus::Paid);
    });

    it('does not take a bare success return on trust when the gateway has nothing validated', function () {
        $transaction = startOnline($this);
        Http::fake(['sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php*' => Http::response(['APIConnect' => 'DONE', 'no_of_trans_found' => 0, 'element' => []])]);

        $this->get(route('gateway.return', ['gateway' => 'sslcommerz', 'outcome' => 'success', 't' => $transaction->tran_id]))
            ->assertOk()->assertSee('Confirming your payment');

        expect(Payment::count())->toBe(0)->and($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Initiated);
    });

    it('ignores callbacks for transactions it never started', function () {
        $this->post(route('gateway.ipn', 'sslcommerz'), ['tran_id' => 'RTLNOPE', 'val_id' => 'X', 'status' => 'VALID'])->assertOk();
        $this->post(route('gateway.ipn', 'no-such-gateway'), ['tran_id' => 'X'])->assertOk();

        expect(Payment::count())->toBe(0)->and(WebhookEvent::sole()->result)->toBe('unknown_transaction');
    });

    it('exempts only the gateway callbacks from CSRF, not the Easy Checkout endpoint', function () {
        // Laravel skips CSRF checks while testing, so ask the middleware which paths it exempts.
        $middleware = app(ValidateCsrfToken::class);
        $exempt = fn (string $path) => (fn () => $this->inExceptArray(Request::create($path, 'POST')))->call($middleware);

        expect($exempt('/payments/gateway/sslcommerz/ipn'))->toBeTrue()
            ->and($exempt('/payments/gateway/sslcommerz/return/success'))->toBeTrue()
            ->and($exempt('/portal/payments/checkout'))->toBeFalse()
            ->and($exempt('/portal/payments/1/online'))->toBeFalse();
    });
});

it('never accepts the gateway method for a hand-entered payment', function () {
    $gatewayMethod = PaymentMethod::where('slug', 'online-payment-sslcommerz')->first();

    $this->actingAs($this->accounts)->post(route('accounts.payment-requests.received', $this->invoice), [
        'payment_method_id' => $gatewayMethod->id, 'amount' => '20000', 'paid_on' => now()->toDateString(),
    ])->assertSessionHasErrors('payment_method_id');

    expect(Payment::count())->toBe(0);
});

it('lists every online attempt for Accounts', function () {
    startOnline($this);

    $this->actingAs($this->accounts)->get(route('accounts.transactions.gateway'))
        ->assertInertia(fn ($page) => $page->component('payments/GatewayTransactions')
            ->has('transactions.data', 1)
            ->where('gateway.sandbox', true));

    expect(Route::has('counselor.transactions.gateway'))->toBeFalse();
    expect(PaymentRequest::count())->toBe(1);
});
