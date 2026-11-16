<?php

use App\Actions\Payments\PaymentLedger;
use App\Actions\Payments\RefundManager;
use App\Enums\Role;
use App\Models\Counselor;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\User;
use App\Support\Reports\FinancialReports;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    Storage::fake('local');

    $this->counselor = User::factory()->create(['name' => 'Karim Counselor'])->assignRole(Role::Counselor->value);
    Counselor::create(['user_id' => $this->counselor->id]);
    $this->student = Student::factory()->create(['counselor_id' => $this->counselor->id]);
    $this->accounts = $this->userWithRole(Role::Accounts);
    $this->admin = $this->userWithRole(Role::SuperAdmin);
    $this->cash = PaymentMethod::where('slug', 'cash-at-office')->first();
    $this->rent = ExpenseCategory::where('slug', 'office-rent')->first();
});

/** An invoice of 20,000 with 15,000 paid, 5,000 of which was refunded; plus a 3,000 expense. */
function seedBooks($test): Payment
{
    $ledger = app(PaymentLedger::class);
    $refunds = app(RefundManager::class);

    $request = $ledger->createRequest($test->student, PaymentType::where('slug', 'visa-processing-fee')->first(), 2000000, CarbonImmutable::today()->addWeek(), null, null, $test->counselor);
    $payment = $ledger->recordReceived($request, $test->cash, 1500000, null, CarbonImmutable::today(), $test->accounts, null);

    $refund = $refunds->request($payment, 500000, 'Overcharged.', $test->counselor);
    $refunds->approve($refund, $test->admin);
    $refunds->process($refund, $test->accounts, $test->cash, null, null);

    Expense::create(['expense_category_id' => $test->rent->id, 'title' => 'Office rent', 'amount' => 300000, 'spent_on' => today(), 'recorded_by' => $test->accounts->id]);

    return $payment;
}

describe('expenses', function () {
    it('lets Accounts record an expense with a bill attached', function () {
        $this->actingAs($this->accounts)->post(route('accounts.expenses.store'), [
            'expense_category_id' => $this->rent->id,
            'title' => 'Office rent, November',
            'amount' => '45000.50',
            'spent_on' => now()->toDateString(),
            'payee' => 'Landlord',
            'attachment' => UploadedFile::fake()->create('bill.pdf', 120, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $expense = Expense::sole();
        expect($expense->amount)->toBe(4500050)
            ->and($expense->recorded_by)->toBe($this->accounts->id)
            ->and($expense->attachment_original_name)->toBe('bill.pdf');
        Storage::disk('local')->assertExists($expense->attachment_path);

        $this->actingAs($this->accounts)->get(route('accounts.expenses.attachment', $expense))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->accounts)->get(route('accounts.expenses.index'))
            ->assertInertia(fn ($page) => $page->component('accounts/Expenses')->has('expenses.data', 1)->where('totals.count', 1));
    });

    it('validates the amount and date', function () {
        $this->actingAs($this->accounts)->post(route('accounts.expenses.store'), [
            'expense_category_id' => $this->rent->id, 'title' => 'Bad', 'amount' => '-5', 'spent_on' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors(['amount', 'spent_on']);

        $this->actingAs($this->accounts)->post(route('accounts.expenses.store'), [
            'expense_category_id' => $this->rent->id, 'title' => 'Zero', 'amount' => '0', 'spent_on' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        expect(Expense::count())->toBe(0);
    });

    it('can be corrected, and removing one keeps the record', function () {
        $expense = Expense::create(['expense_category_id' => $this->rent->id, 'title' => 'Rent', 'amount' => 100000, 'spent_on' => today(), 'recorded_by' => $this->accounts->id]);

        $this->actingAs($this->accounts)->post(route('accounts.expenses.update', $expense), [
            'expense_category_id' => $this->rent->id, 'title' => 'Rent (corrected)', 'amount' => '1200', 'spent_on' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        expect($expense->fresh()->amount)->toBe(120000)->and($expense->fresh()->title)->toBe('Rent (corrected)');

        $this->actingAs($this->accounts)->delete(route('accounts.expenses.destroy', $expense))->assertSessionHasNoErrors();
        expect(Expense::count())->toBe(0)->and(Expense::withTrashed()->count())->toBe(1);
    });

    it('lets Accounts add a category', function () {
        $this->actingAs($this->accounts)->post(route('accounts.expense-categories.store'), ['name' => 'Software'])->assertSessionHasNoErrors();
        $this->actingAs($this->accounts)->post(route('accounts.expense-categories.store'), ['name' => 'Software'])->assertSessionHasErrors('name');

        expect(ExpenseCategory::where('name', 'Software')->count())->toBe(1);
    });

    it('is closed to people without the expenses permission', function () {
        $this->actingAs($this->counselor)->get(route('accounts.expenses.index'))->assertForbidden();
        expect(Route::has('counselor.expenses.index'))->toBeFalse();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['area.accounts', 'payments.view']);
        $this->actingAs($viewer)->get(route('accounts.expenses.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('accounts.expenses.store'), [])->assertForbidden();
    });
});

describe('financial figures', function () {
    it('nets income, refunds and expenses into profit', function () {
        seedBooks($this);

        $summary = FinancialReports::summary(CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()->endOfDay());

        expect($summary['income'])->toBe(1500000)
            ->and($summary['payments'])->toBe(1)
            ->and($summary['refunds'])->toBe(500000)
            ->and($summary['net_income'])->toBe(1000000)
            ->and($summary['expenses'])->toBe(300000)
            ->and($summary['profit'])->toBe(700000)
            ->and($summary['invoiced'])->toBe(2000000)
            ->and($summary['outstanding'])->toBe(500000);
    });

    it('leaves money outside the period out', function () {
        seedBooks($this);

        $summary = FinancialReports::summary(CarbonImmutable::now()->subYear()->startOfYear(), CarbonImmutable::now()->subYear()->endOfYear());

        expect($summary['income'])->toBe(0)->and($summary['refunds'])->toBe(0)->and($summary['expenses'])->toBe(0)
            // Dues are today's position whatever the period.
            ->and($summary['outstanding'])->toBe(500000);
    });

    it('breaks collection down by counselor, with unassigned students apart', function () {
        seedBooks($this);
        $orphan = Student::factory()->create(['counselor_id' => null]);
        app(PaymentLedger::class)->createRequest($orphan, PaymentType::first(), 700000, null, null, null, $this->admin);

        $rows = FinancialReports::counselorCollection(CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()->endOfDay())->keyBy('counselor');

        expect($rows['Karim Counselor'])->toMatchArray(['students' => 1, 'invoiced' => 2000000, 'collected' => 1500000, 'refunded' => 500000, 'outstanding' => 500000])
            ->and($rows['Unassigned'])->toMatchArray(['students' => 1, 'invoiced' => 700000, 'collected' => 0, 'outstanding' => 700000]);
    });

    it('lists money in and money out together in the transaction history', function () {
        seedBooks($this);

        $this->actingAs($this->accounts)->get(route('accounts.transactions.index'))
            ->assertInertia(fn ($page) => $page->component('payments/Transactions')
                ->has('transactions.data', 2)
                ->where('totals.in', '৳15,000')
                ->where('totals.out', '৳5,000')
                ->where('totals.net', '৳10,000'));

        $this->actingAs($this->accounts)->get(route('accounts.transactions.index', ['direction' => 'out']))
            ->assertInertia(fn ($page) => $page->has('transactions.data', 1)->where('transactions.data.0.direction', 'out'));
    });
});

describe('report screens', function () {
    it('shows Accounts the financial report, counselor collection and student accounts', function () {
        seedBooks($this);

        $this->actingAs($this->accounts)->get(route('accounts.reports.financial'))
            ->assertInertia(fn ($page) => $page->component('reports/Financial')
                ->where('summary.income', '৳15,000')
                ->where('summary.profit', '৳7,000')
                ->where('summary.profit_negative', false)
                ->has('monthly', 1)
                ->has('byType', 1)
                ->has('byCategory', 1));

        $this->actingAs($this->accounts)->get(route('accounts.reports.counselors'))
            ->assertInertia(fn ($page) => $page->component('reports/Counselors')->where('totals.collected', '৳15,000'));

        $this->actingAs($this->accounts)->get(route('accounts.reports.students'))
            ->assertInertia(fn ($page) => $page->component('reports/Students')
                ->has('students.data', 1)
                ->where('students.data.0.due', '৳5,000')
                ->where('students.data.0.refunded', '৳5,000'));
    });

    it('keeps financial reports away from counselors', function () {
        $this->actingAs($this->counselor)->get(route('accounts.reports.financial'))->assertForbidden();
        expect(Route::has('counselor.reports.financial'))->toBeFalse();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['area.accounts', 'payments.view']);
        $this->actingAs($viewer)->get(route('accounts.reports.financial'))->assertForbidden();
        $this->actingAs($viewer)->get(route('accounts.reports.download', ['report' => 'transactions', 'format' => 'csv']))->assertForbidden();
    });

    it('rejects a period that ends before it starts', function () {
        $this->actingAs($this->accounts)->get(route('accounts.reports.financial', ['from' => '2026-05-10', 'to' => '2026-05-01']))->assertSessionHasErrors('to');
    });
});

describe('exports', function () {
    it('exports every report as CSV', function (string $report, string $expected) {
        seedBooks($this);

        $response = $this->actingAs($this->accounts)->get(route('accounts.reports.download', ['report' => $report, 'format' => 'csv']));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        expect($response->streamedContent())->toContain($expected);
    })->with([
        'summary' => ['summary', 'Month,Income,Refunds,Expenses,Profit'],
        'transactions' => ['transactions', 'Refund'],
        'invoices' => ['invoices', 'INV-'],
        'dues' => ['dues', 'Days overdue'],
        'refunds' => ['refunds', 'RFD-'],
        'expenses' => ['expenses', 'Office rent'],
        'counselors' => ['counselors', 'Karim Counselor'],
        'students' => ['students', 'Total fees'],
    ]);

    it('exports Excel and PDF files', function () {
        seedBooks($this);

        $xlsx = $this->actingAs($this->accounts)->get(route('accounts.reports.download', ['report' => 'transactions', 'format' => 'xlsx']));
        $xlsx->assertOk();
        // An .xlsx file is a zip archive.
        expect(substr($xlsx->streamedContent(), 0, 2))->toBe('PK');

        $pdf = $this->actingAs($this->accounts)->get(route('accounts.reports.download', ['report' => 'counselors', 'format' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect(substr($pdf->getContent(), 0, 4))->toBe('%PDF');
    });

    it('neutralises spreadsheet formulas typed into user fields', function () {
        Expense::create(['expense_category_id' => $this->rent->id, 'title' => '=HYPERLINK("http://evil.test","x")', 'amount' => 100, 'spent_on' => today(), 'recorded_by' => $this->accounts->id]);

        $csv = $this->actingAs($this->accounts)->get(route('accounts.reports.download', ['report' => 'expenses', 'format' => 'csv']))->streamedContent();

        expect($csv)->toContain("'=HYPERLINK")->not->toContain("\n=HYPERLINK")->not->toContain(',=HYPERLINK');
    });

    it('records each export in the activity log', function () {
        $this->actingAs($this->accounts)->get(route('accounts.reports.download', ['report' => 'dues', 'format' => 'csv']))->assertOk();

        $entry = Activity::where('log_name', 'finance')->latest('id')->first();
        expect($entry?->causer_id)->toBe($this->accounts->id)->and($entry?->description)->toContain('Due payments');
    });

    it('404s for unknown reports and formats', function () {
        $this->actingAs($this->accounts)->get(route('accounts.reports.download', ['report' => 'secrets', 'format' => 'csv']))->assertNotFound();
        $this->actingAs($this->accounts)->get(route('accounts.reports.download', ['report' => 'dues', 'format' => 'exe']))->assertNotFound();
    });
});

describe('invoices and receipts', function () {
    it('gives the student, counselor and Accounts the invoice PDF', function () {
        $payment = seedBooks($this);

        foreach ([[$this->student->user, 'portal.payments.invoice'], [$this->counselor, 'counselor.payment-requests.invoice'], [$this->accounts, 'accounts.payment-requests.invoice']] as [$user, $route]) {
            $response = $this->actingAs($user)->get(route($route, $payment->payment_request_id));
            $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
            expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
        }
    });

    it('gives a receipt only for confirmed payments', function () {
        $payment = seedBooks($this);

        $this->actingAs($this->student->user)->get(route('portal.payments.receipt', $payment))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $pending = app(PaymentLedger::class)->submitPayment($payment->request->fresh(), PaymentMethod::where('slug', 'bkash-nagad-rocket')->first(), 100000, 'TRX-P', CarbonImmutable::today(), null, $this->student->user);
        $this->actingAs($this->student->user)->get(route('portal.payments.receipt', $pending))->assertNotFound();
    });

    it("hides other students' documents", function () {
        $payment = seedBooks($this);
        $stranger = Student::factory()->create();
        $otherCounselor = User::factory()->create()->assignRole(Role::Counselor->value);

        $this->actingAs($stranger->user)->get(route('portal.payments.invoice', $payment->payment_request_id))->assertForbidden();
        $this->actingAs($stranger->user)->get(route('portal.payments.receipt', $payment))->assertForbidden();
        $this->actingAs($otherCounselor)->get(route('counselor.payments.receipt', $payment))->assertForbidden();
    });
});

describe('payment lists', function () {
    it('lists dues most overdue first and partial payments on their own', function () {
        seedBooks($this);

        $this->actingAs($this->accounts)->get(route('accounts.payment-requests.due'))
            ->assertInertia(fn ($page) => $page->component('payments/Requests')->where('title', 'Due payments')->has('requests.data', 1));
        $this->actingAs($this->accounts)->get(route('accounts.payment-requests.partial'))
            ->assertInertia(fn ($page) => $page->where('title', 'Partial payments')->has('requests.data', 1));
        $this->actingAs($this->counselor)->get(route('counselor.payment-requests.due'))
            ->assertInertia(fn ($page) => $page->has('requests.data', 1));
    });

    it('lists receipts and bank transactions for Accounts', function () {
        seedBooks($this);

        $this->actingAs($this->accounts)->get(route('accounts.payments.receipts'))
            ->assertInertia(fn ($page) => $page->component('payments/Payments')->where('title', 'Payment receipts')->has('payments.data', 1)
                ->where('payments.data.0.receipt_url', fn ($url) => str_contains($url, '/receipt')));
        // The only payment so far was cash, so the bank list is empty.
        $this->actingAs($this->accounts)->get(route('accounts.transactions.bank'))
            ->assertInertia(fn ($page) => $page->where('title', 'Bank transactions')->has('payments.data', 0));
    });

    it('shows a student only what they still owe on the due page, with refund totals on the dashboard', function () {
        seedBooks($this);

        $this->actingAs($this->student->user)->get(route('portal.payments.due'))
            ->assertInertia(fn ($page) => $page->component('portal/Payments')->where('dueOnly', true)->has('requests', 1)->where('totals.refunded', '৳5,000'));
    });
});
