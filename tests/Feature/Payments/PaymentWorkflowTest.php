<?php

use App\Actions\Payments\PaymentLedger;
use App\Enums\PaymentRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Http\Controllers\Payments\PaymentDocumentController;
use App\Models\Counselor;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\PaymentStatusHistory;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\User;
use App\Notifications\FileOpeningFeeNeeded;
use App\Notifications\PaymentDecided;
use App\Notifications\PaymentRequested;
use App\Notifications\PaymentSubmitted;
use App\Support\Money;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    Storage::fake('local');

    $this->counselor = User::factory()->create()->assignRole(Role::Counselor->value);
    Counselor::create(['user_id' => $this->counselor->id]);
    $this->student = Student::factory()->create(['counselor_id' => $this->counselor->id]);
    $this->accounts = $this->userWithRole(Role::Accounts);
    $this->visaFee = PaymentType::where('slug', 'visa-processing-fee')->first();
    $this->mobile = PaymentMethod::where('slug', 'bkash-nagad-rocket')->first();
    $this->bank = PaymentMethod::where('slug', 'bank-transfer-deposit')->first();
    $this->cash = PaymentMethod::where('slug', 'cash-at-office')->first();
});

/** Raise an invoice as the counselor and return it. */
function raise($test, string $amount = '20000', array $overrides = []): PaymentRequest
{
    $test->actingAs($test->counselor)->post(route('counselor.payment-requests.store'), array_merge([
        'student_id' => $test->student->id,
        'payment_type_id' => $test->visaFee->id,
        'amount' => $amount,
        'due_date' => now()->addDays(7)->toDateString(),
    ], $overrides))->assertSessionHasNoErrors();

    return PaymentRequest::latest('id')->firstOrFail();
}

/** Submit a mobile-banking payment as the student and return it. */
function pay($test, PaymentRequest $request, string $amount, string $reference): Payment
{
    $test->actingAs($test->student->user)->post(route('portal.payments.store', $request), [
        'payment_method_id' => $test->mobile->id,
        'amount' => $amount,
        'transaction_reference' => $reference,
        'paid_on' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    return Payment::latest('id')->firstOrFail();
}

describe('raising a payment request', function () {
    it('gives it a unique invoice number, records history and tells the student', function () {
        Notification::fake();

        $first = raise($this);
        $second = raise($this, '5000.50');

        expect($first->invoice_number)->toStartWith('INV-')
            ->and($second->invoice_number)->not->toBe($first->invoice_number)
            ->and($first->amount)->toBe(2000000)
            ->and($second->amount)->toBe(500050)
            ->and($first->status)->toBe(PaymentRequestStatus::Unpaid)
            ->and($first->requested_by)->toBe($this->counselor->id)
            ->and($first->statusHistory()->count())->toBe(1);

        Notification::assertSentTo($this->student->user, PaymentRequested::class);
    });

    it("stops counselors billing other counselors' students", function () {
        $other = Student::factory()->create();

        $this->actingAs($this->counselor)->post(route('counselor.payment-requests.store'), [
            'student_id' => $other->id, 'payment_type_id' => $this->visaFee->id, 'amount' => '100',
        ])->assertForbidden();
    });

    it('validates the amount', function () {
        $this->actingAs($this->counselor)->post(route('counselor.payment-requests.store'), [
            'student_id' => $this->student->id, 'payment_type_id' => $this->visaFee->id, 'amount' => '12.345',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($this->counselor)->post(route('counselor.payment-requests.store'), [
            'student_id' => $this->student->id, 'payment_type_id' => $this->visaFee->id, 'amount' => '0',
        ])->assertSessionHasErrors('amount');
    });

    it('cancels only invoices with no payments', function () {
        $request = raise($this);

        $this->actingAs($this->counselor)->patch(route('counselor.payment-requests.cancel', $request), ['reason' => 'Raised by mistake'])->assertSessionHasNoErrors();
        expect($request->fresh()->status)->toBe(PaymentRequestStatus::Cancelled);

        $second = raise($this);
        pay($this, $second, '20000', 'TRX1');
        $this->actingAs($this->counselor)->patch(route('counselor.payment-requests.cancel', $second), ['reason' => 'x'])->assertSessionHasErrors('request');
    });
});

describe('submitting a payment', function () {
    it('puts the invoice in pending verification and alerts Accounts', function () {
        $request = raise($this);
        Notification::fake();

        $payment = pay($this, $request, '20000', 'BK123');

        expect($payment->status)->toBe(PaymentStatus::Pending)
            ->and($request->fresh()->status)->toBe(PaymentRequestStatus::Pending)
            ->and($request->fresh()->amount_paid)->toBe(0);

        Notification::assertSentTo($this->accounts, PaymentSubmitted::class);
    });

    it('stores proof of payment privately', function () {
        $request = raise($this);

        $this->actingAs($this->student->user)->post(route('portal.payments.store', $request), [
            'payment_method_id' => $this->bank->id, 'amount' => '20000', 'transaction_reference' => 'DEP-1',
            'paid_on' => now()->toDateString(), 'evidence' => UploadedFile::fake()->image('slip.jpg'),
        ])->assertSessionHasNoErrors();

        $payment = Payment::sole();
        Storage::disk('local')->assertExists($payment->evidence_path);

        $this->actingAs($this->student->user)->get(route('portal.payments.evidence', $payment))->assertOk();
        $this->actingAs($this->accounts)->get(route('accounts.payments.evidence', $payment))->assertOk();
        $this->actingAs(Student::factory()->create()->user)->get(route('portal.payments.evidence', $payment))->assertForbidden();
    });

    it('enforces the method rules, the balance and unique references', function () {
        $request = raise($this);
        $student = $this->student->user;
        $base = ['paid_on' => now()->toDateString()];

        // Bank transfers need a reference and proof.
        $this->actingAs($student)->post(route('portal.payments.store', $request), [...$base, 'payment_method_id' => $this->bank->id, 'amount' => '20000'])
            ->assertSessionHasErrors('transaction_reference');
        $this->actingAs($student)->post(route('portal.payments.store', $request), [...$base, 'payment_method_id' => $this->bank->id, 'amount' => '20000', 'transaction_reference' => 'D1'])
            ->assertSessionHasErrors('evidence');
        // More than the outstanding balance.
        $this->actingAs($student)->post(route('portal.payments.store', $request), [...$base, 'payment_method_id' => $this->mobile->id, 'amount' => '20001', 'transaction_reference' => 'T1'])
            ->assertSessionHasErrors('amount');
        // Staff-only methods can't be used from the portal.
        $this->actingAs($student)->post(route('portal.payments.store', $request), [...$base, 'payment_method_id' => $this->cash->id, 'amount' => '20000'])
            ->assertStatus(422);

        pay($this, $request, '10000', 'SAME');
        $this->actingAs($student)->post(route('portal.payments.store', $request), [...$base, 'payment_method_id' => $this->mobile->id, 'amount' => '10000', 'transaction_reference' => 'SAME'])
            ->assertSessionHasErrors('transaction_reference');
        // 10,000 is already awaiting verification, so only 10,000 more fits.
        $this->actingAs($student)->post(route('portal.payments.store', $request), [...$base, 'payment_method_id' => $this->mobile->id, 'amount' => '10001', 'transaction_reference' => 'T2'])
            ->assertSessionHasErrors('amount');
    });

    it("stops a student paying someone else's invoice", function () {
        $request = raise($this);

        $this->actingAs(Student::factory()->create()->user)->post(route('portal.payments.store', $request), [
            'payment_method_id' => $this->mobile->id, 'amount' => '100', 'transaction_reference' => 'X', 'paid_on' => now()->toDateString(),
        ])->assertForbidden();
    });
});

describe('confirmation by Accounts', function () {
    it('marks the invoice paid, issues a receipt and tells the student and counselor', function () {
        $request = raise($this);
        $payment = pay($this, $request, '20000', 'BK1');
        Notification::fake();

        $this->actingAs($this->accounts)->patch(route('accounts.payments.confirm', $payment))->assertSessionHasNoErrors();

        $payment->refresh();
        $request->refresh();

        expect($payment->status)->toBe(PaymentStatus::Confirmed)
            ->and($payment->receipt_number)->toStartWith('RCP-')
            ->and($payment->confirmed_by)->toBe($this->accounts->id)
            ->and($request->status)->toBe(PaymentRequestStatus::Paid)
            ->and($request->amount_paid)->toBe(2000000)
            ->and($request->paid_at)->not->toBeNull();

        Notification::assertSentTo($this->student->user, PaymentDecided::class);
        Notification::assertSentTo($this->counselor, PaymentDecided::class);
    });

    it('never counts a payment twice', function () {
        $request = raise($this);
        $payment = pay($this, $request, '20000', 'BK1');

        $this->actingAs($this->accounts)->patch(route('accounts.payments.confirm', $payment));
        $this->actingAs($this->accounts)->patch(route('accounts.payments.confirm', $payment))->assertSessionHasErrors('payment');

        expect($request->fresh()->amount_paid)->toBe(2000000)
            ->and(Payment::whereNotNull('receipt_number')->count())->toBe(1);
    });

    it('supports partial payments and outstanding balances', function () {
        $request = raise($this);

        $first = pay($this, $request, '8000', 'P1');
        $this->actingAs($this->accounts)->patch(route('accounts.payments.confirm', $first))->assertSessionHasNoErrors();
        expect($request->fresh()->status)->toBe(PaymentRequestStatus::Partial)
            ->and($request->fresh()->outstanding())->toBe(1200000);

        $second = pay($this, $request, '12000', 'P2');
        $this->actingAs($this->accounts)->patch(route('accounts.payments.confirm', $second))->assertSessionHasNoErrors();
        expect($request->fresh()->status)->toBe(PaymentRequestStatus::Paid);
    });

    it('rejects with a reason and lets the student try again', function () {
        $request = raise($this);
        $payment = pay($this, $request, '20000', 'BAD');

        $this->actingAs($this->accounts)->patch(route('accounts.payments.reject', $payment))->assertSessionHasErrors('reason');
        $this->actingAs($this->accounts)->patch(route('accounts.payments.reject', $payment), ['reason' => 'Reference not found'])->assertSessionHasNoErrors();

        expect($payment->fresh()->status)->toBe(PaymentStatus::Rejected)
            ->and($request->fresh()->status)->toBe(PaymentRequestStatus::Rejected)
            ->and($request->fresh()->amount_paid)->toBe(0);

        // The rejected reference may be corrected and resubmitted.
        pay($this, $request, '20000', 'BAD');
        expect($request->fresh()->status)->toBe(PaymentRequestStatus::Pending);
    });

    it('lets Accounts verify first and confirm later', function () {
        $request = raise($this);
        $payment = pay($this, $request, '20000', 'V1');

        $this->actingAs($this->accounts)->patch(route('accounts.payments.verify', $payment))->assertSessionHasNoErrors();
        expect($payment->fresh()->status)->toBe(PaymentStatus::Verified)
            ->and($request->fresh()->status)->toBe(PaymentRequestStatus::Pending);

        $this->actingAs($this->accounts)->patch(route('accounts.payments.confirm', $payment));
        expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
            ->and($payment->fresh()->verified_by)->toBe($this->accounts->id);
    });

    it('records cash received at the office in one step', function () {
        $request = raise($this);

        $this->actingAs($this->accounts)->post(route('accounts.payment-requests.received', $request), [
            'payment_method_id' => $this->cash->id, 'amount' => '20000', 'paid_on' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        expect(Payment::sole()->status)->toBe(PaymentStatus::Confirmed)
            ->and($request->fresh()->status)->toBe(PaymentRequestStatus::Paid);
    });

    it('writes every status change to the history', function () {
        $request = raise($this);
        $payment = pay($this, $request, '20000', 'H1');
        $this->actingAs($this->accounts)->patch(route('accounts.payments.confirm', $payment));

        expect($request->statusHistory()->pluck('to_status')->all())->toBe(['paid', 'pending', 'unpaid'])
            ->and($payment->statusHistory()->pluck('to_status')->all())->toBe(['confirmed', 'pending'])
            ->and(PaymentStatusHistory::whereNull('changed_by')->count())->toBe(0);
    });
});

describe('who may confirm', function () {
    it('never lets counselors confirm or reject payments (REQ §27 rule 6)', function () {
        $payment = pay($this, raise($this), '20000', 'C1');

        $this->actingAs($this->counselor)->patch(route('accounts.payments.confirm', $payment))->assertForbidden();
        $this->actingAs($this->counselor)->patch(route('admin.payments.reject', $payment), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->student->user)->patch(route('accounts.payments.confirm', $payment))->assertForbidden();

        expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
    });

    it('stops whoever raised an invoice confirming its payments, Super Admin included', function () {
        $admin = $this->userWithRole(Role::SuperAdmin);
        $request = app(PaymentLedger::class)->createRequest($this->student, $this->visaFee, 500000, null, null, null, $admin);
        $payment = pay($this, $request, '5000', 'SA1');

        $this->actingAs($admin)->patch(route('admin.payments.confirm', $payment))->assertSessionHasErrors('payment');
        expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);

        // Someone else in Accounts can.
        $this->actingAs($this->accounts)->patch(route('accounts.payments.confirm', $payment))->assertSessionHasNoErrors();
        expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed);
    });
});

describe('visibility', function () {
    it('shows counselors only their own students, Accounts everything, students their own', function () {
        raise($this);
        $other = Student::factory()->create();
        app(PaymentLedger::class)->createRequest($other, $this->visaFee, 100000, null, null, null, $this->accounts);

        $this->actingAs($this->counselor)->get(route('counselor.payment-requests.index'))->assertInertia(fn ($p) => $p->where('requests.total', 1));
        $this->actingAs($this->accounts)->get(route('accounts.payment-requests.index'))->assertInertia(fn ($p) => $p->where('requests.total', 2));
        $this->actingAs($this->student->user)->get(route('portal.payments.index'))->assertInertia(fn ($p) => $p->has('requests', 1)->where('totals.total', '৳20,000'));

        $foreign = PaymentRequest::where('student_id', $other->id)->first();
        $this->actingAs($this->counselor)->get(route('counselor.payment-requests.show', $foreign))->assertForbidden();
    });

    it('keeps revenue off the dashboard of staff without payment access', function () {
        $this->actingAs($this->userWithRole(Role::LeadManager))->get(route('admin.dashboard'))
            ->assertInertia(fn ($p) => $p->has('stats', 4)->where('monthlyRevenue', null));
        $this->actingAs($this->userWithRole(Role::SuperAdmin))->get(route('admin.dashboard'))
            ->assertInertia(fn ($p) => $p->has('stats', 6)->has('monthlyRevenue', 12));
    });

    it('renders the three dashboards with live figures', function () {
        $request = raise($this);
        $payment = pay($this, $request, '20000', 'D1');
        $this->actingAs($this->accounts)->patch(route('accounts.payments.confirm', $payment))->assertSessionHasNoErrors();

        $this->actingAs($this->counselor)->get(route('counselor.dashboard'))->assertInertia(fn ($p) => $p->component('counselor/Dashboard')->has('stats', 11)->has('payments', 1));
        $this->actingAs($this->accounts)->get(route('accounts.dashboard'))->assertInertia(fn ($p) => $p->component('accounts/Dashboard')->where('stats.0.value', '৳20,000')->has('monthly', 12)->has('methods', 1));
        $this->actingAs($this->student->user)->get(route('portal.dashboard'))->assertInertia(fn ($p) => $p->component('portal/Dashboard')->where('money.paid', '৳20,000')->where('money.next', null));
    });
});

describe('automation', function () {
    it('raises the file-opening fee when a file is opened, once an amount is set', function () {
        PaymentType::where('slug', 'file-opening-fee')->update(['default_amount' => 500000]);

        $this->actingAs($this->counselor)->post(route('counselor.students.files.store', $this->student), []);

        $request = PaymentRequest::sole();
        expect($request->type->slug)->toBe('file-opening-fee')
            ->and($request->amount)->toBe(500000)
            ->and($request->student_file_id)->not->toBeNull()
            ->and($request->due_date->isSameDay(now()->addDays(7)))->toBeTrue();
    });

    it('asks the counselor to raise it by hand while no amount is set', function () {
        Notification::fake();

        $this->actingAs($this->counselor)->post(route('counselor.students.files.store', $this->student), []);

        expect(PaymentRequest::count())->toBe(0);
        Notification::assertSentTo($this->counselor, FileOpeningFeeNeeded::class);
    });

    it('marks unpaid invoices overdue after their due date', function () {
        $request = raise($this, '20000', ['due_date' => now()->addDay()->toDateString()]);

        $this->travel(3)->days();
        $this->artisan('payments:mark-overdue')->assertSuccessful();

        expect($request->fresh()->status)->toBe(PaymentRequestStatus::Due);
    });
});

describe('settings', function () {
    it('lets admins add payment types and protects ones in use', function () {
        $admin = $this->userWithRole(Role::SuperAdmin);

        $this->actingAs($admin)->post(route('admin.payment-types.store'), [
            'name' => 'Courier Fee', 'default_amount' => '1500', 'generate_on_file_open' => false, 'is_active' => true,
        ])->assertSessionHasNoErrors();
        expect(PaymentType::where('slug', 'courier-fee')->first()->default_amount)->toBe(150000);

        raise($this);
        $this->actingAs($admin)->delete(route('admin.payment-types.destroy', $this->visaFee))->assertSessionHasErrors('type');
        $this->actingAs($this->counselor)->get(route('admin.payment-types.index'))->assertForbidden();
    });

    it('lets Accounts maintain payment methods and their instructions', function () {
        $this->actingAs($this->accounts)->put(route('accounts.payment-methods.update', $this->mobile), [
            'name' => $this->mobile->name, 'kind' => 'mobile_banking', 'instructions' => 'bKash merchant: 01XXXXXXXXX',
            'requires_reference' => true, 'requires_evidence' => false, 'student_selectable' => true, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        expect($this->mobile->fresh()->instructions)->toBe('bKash merchant: 01XXXXXXXXX');
    });
});

it('converts money without floating-point error', function () {
    expect(Money::toMinor('5000'))->toBe(500000)
        ->and(Money::toMinor('5,000.5'))->toBe(500050)
        ->and(Money::toMinor('0.07'))->toBe(7)
        ->and(Money::toDecimal(500050))->toBe('5000.50')
        ->and(Money::format(500000))->toBe('৳5,000')
        ->and(Money::format(500050))->toBe('৳5,000.50');

    expect(fn () => Money::toMinor('-5'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Money::toMinor('1.234'))->toThrow(InvalidArgumentException::class);
});

it('lets Accounts record a bank transfer as received without uploading proof', function () {
    $request = raise($this);

    // Students must upload a slip for bank transfers; Accounts vouches for the money itself.
    $this->actingAs($this->accounts)->post(route('accounts.payment-requests.received', $request), [
        'payment_method_id' => $this->bank->id, 'amount' => '20000', 'transaction_reference' => 'BANK-55', 'paid_on' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(PaymentRequestStatus::Paid);

    $second = raise($this);
    $this->actingAs($this->student->user)->post(route('portal.payments.store', $second), [
        'payment_method_id' => $this->bank->id, 'amount' => '20000', 'transaction_reference' => 'BANK-56', 'paid_on' => now()->toDateString(),
    ])->assertSessionHasErrors('evidence');
});

describe('internal note', function () {
    it('lets Accounts save a private note that the student cannot see or set', function () {
        $payment = pay($this, raise($this), '20000', 'TX-NOTE');

        $this->actingAs($this->accounts)
            ->patch(route('accounts.payments.internal-note', $payment), ['internal_note' => 'Matches bank statement'])
            ->assertSessionHasNoErrors();

        expect($payment->fresh()->internal_note)->toBe('Matches bank statement');

        $this->actingAs($this->accounts)->get(route('accounts.payments.show', $payment))
            ->assertInertia(fn ($page) => $page->where('payment.internal_note', 'Matches bank statement'));

        $this->actingAs($this->student->user)->get(route('portal.payments.history'))
            ->assertDontSee('Matches bank statement');
        expect(Route::has('portal.payments.internal-note'))->toBeFalse();
    });
});

describe('document copy label', function () {
    it('gives the student the applicant copy and staff the file copy', function () {
        $label = new ReflectionMethod(PaymentDocumentController::class, 'copyLabel');

        $this->actingAs($this->student->user);
        expect($label->invoke(null, $this->student))->toBe('APPLICANT’S COPY');

        $this->actingAs($this->counselor);
        expect($label->invoke(null, $this->student))->toBe('FILE COPY');

        $this->actingAs($this->userWithRole(Role::SuperAdmin));
        expect($label->invoke(null, $this->student))->toBe('OFFICE COPY');
    });

    it('prints the label on the invoice page', function () {
        $invoice = raise($this)->load(['student.user', 'student.profile', 'type', 'file.country', 'payments']);
        $company = ['name' => 'RTL', 'tagline' => null, 'address' => null, 'phone' => null, 'email' => null];

        expect(view('pdf.invoice', ['invoice' => $invoice, 'company' => $company, 'copy' => 'FILE COPY'])->render())->toContain('FILE COPY');
    });
});
