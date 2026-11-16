<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PaymentRequestStatus;
use App\Enums\RefundStatus;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\PaymentRequest;
use App\Models\Refund;
use App\Models\Student;
use App\Support\Money;
use App\Support\Reports\FinancialReports;
use App\Support\Reports\ReportExporter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * REQ §9 and §9.1: "Financial Reports", "Monthly / Yearly Reports",
 * "Counselor-wise Collection", "Student-wise Accounts", "Export Reports".
 * Route middleware checks reports.financial / reports.export.
 */
class FinancialReportController extends Controller
{
    use ResolvesAreaRoutes;

    /** The exports on offer: key => [title, what it contains, uses the date range]. */
    private const EXPORTS = [
        'summary' => ['Financial summary', 'Income, refunds, expenses and profit for each month in the period.', true],
        'transactions' => ['Transaction history', 'Every confirmed payment and paid refund in the period.', true],
        'invoices' => ['Invoices', 'Invoices raised in the period, with paid and outstanding amounts.', true],
        'dues' => ['Due payments', 'Every invoice still owed money today, oldest due date first.', false],
        'refunds' => ['Refunds', 'Refunds requested in the period and where each one stands.', true],
        'expenses' => ['Expenses', 'Expense records dated in the period.', true],
        'counselors' => ['Counselor-wise collection', 'Invoiced, collected, refunded and outstanding per counselor.', true],
        'students' => ['Student-wise accounts', 'Fees, payments, refunds and balance for every billed student.', false],
    ];

    public function index(Request $request): Response
    {
        [$from, $to, $filters] = $this->period($request);
        $summary = FinancialReports::summary($from, $to);
        $share = fn (int $part, int $whole) => $whole > 0 ? (int) round($part / $whole * 100) : 0;
        $breakdown = fn ($rows, int $whole) => $rows->map(fn (array $r) => [...$r, 'total' => Money::format($r['total']), 'share' => $share($r['total'], $whole)])->values();

        return Inertia::render('reports/Financial', [
            'filters' => $filters,
            'summary' => [
                'income' => Money::format($summary['income']),
                'payments' => $summary['payments'],
                'refunds' => Money::format($summary['refunds']),
                'refund_count' => $summary['refund_count'],
                'net_income' => Money::format($summary['net_income']),
                'expenses' => Money::format($summary['expenses']),
                'profit' => Money::format(abs($summary['profit'])),
                'profit_negative' => $summary['profit'] < 0,
                'invoiced' => Money::format($summary['invoiced']),
                'outstanding' => Money::format($summary['outstanding']),
                'overdue' => Money::format($summary['overdue']),
            ],
            'monthly' => array_map(fn (array $m) => [
                'label' => $m['label'],
                'income' => Money::format($m['income']),
                'refunds' => Money::format($m['refunds']),
                'expenses' => Money::format($m['expenses']),
                'profit' => Money::format(abs($m['profit'])),
                'profit_negative' => $m['profit'] < 0,
                'value' => $m['income'],
                'display' => Money::format($m['income']),
            ], FinancialReports::monthly($from, $to)),
            'byType' => $breakdown(FinancialReports::incomeByType($from, $to), $summary['income']),
            'byMethod' => $breakdown(FinancialReports::incomeByMethod($from, $to), $summary['income']),
            'byCategory' => $breakdown(FinancialReports::expensesByCategory($from, $to), $summary['expenses']),
            'exportsUrl' => $request->user()->can('reports.export') ? $this->areaRoute($request, 'reports.downloads', array_filter(['from' => $filters['from'], 'to' => $filters['to']])) : null,
        ]);
    }

    public function counselors(Request $request): Response
    {
        [$from, $to, $filters] = $this->period($request);
        $rows = FinancialReports::counselorCollection($from, $to);

        return Inertia::render('reports/Counselors', [
            'filters' => $filters,
            'rows' => $rows->map(fn (array $r) => [
                'counselor' => $r['counselor'],
                'students' => $r['students'],
                'invoiced' => Money::format($r['invoiced']),
                'collected' => Money::format($r['collected']),
                'refunded' => Money::format($r['refunded']),
                'outstanding' => Money::format($r['outstanding']),
                'value' => $r['collected'],
            ])->values(),
            'totals' => [
                'invoiced' => Money::format((int) $rows->sum('invoiced')),
                'collected' => Money::format((int) $rows->sum('collected')),
                'refunded' => Money::format((int) $rows->sum('refunded')),
                'outstanding' => Money::format((int) $rows->sum('outstanding')),
            ],
            'exportUrl' => $request->user()->can('reports.export') ? $this->areaRoute($request, 'reports.download', ['report' => 'counselors', 'format' => 'xlsx', 'from' => $filters['from'], 'to' => $filters['to']]) : null,
        ]);
    }

    public function students(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'due' => ['nullable', Rule::in(['1'])],
        ]);

        return Inertia::render('reports/Students', [
            'students' => FinancialReports::studentAccounts($filters['search'] ?? null, ($filters['due'] ?? null) === '1')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Student $s) => [
                    'id' => $s->id,
                    'name' => $s->user->name,
                    'student_number' => $s->student_number,
                    'counselor' => $s->counselor?->name,
                    'fees' => Money::format((int) $s->getAttribute('fees')),
                    'paid' => Money::format((int) $s->getAttribute('paid')),
                    'refunded' => Money::format((int) $s->getAttribute('refunded')),
                    'refunded_minor' => (int) $s->getAttribute('refunded'),
                    'due' => Money::format((int) $s->getAttribute('due')),
                    'due_minor' => (int) $s->getAttribute('due'),
                    'url' => $this->areaHas($request, 'students.show') && $request->user()->can('view', $s)
                        ? $this->areaRoute($request, 'students.show', ['student' => $s->id, 'tab' => 'payments'])
                        : $this->areaRoute($request, 'payment-requests.index', ['search' => $s->student_number]),
                ]),
            'filters' => ['search' => $filters['search'] ?? '', 'due' => $filters['due'] ?? ''],
            'exportUrl' => $request->user()->can('reports.export') ? $this->areaRoute($request, 'reports.download', ['report' => 'students', 'format' => 'xlsx']) : null,
        ]);
    }

    /** REQ §9.1 "Export Reports": pick a report, a period and a format. */
    public function downloads(Request $request): Response
    {
        [, , $filters] = $this->period($request);

        return Inertia::render('reports/Exports', [
            'filters' => $filters,
            'reports' => collect(self::EXPORTS)->map(fn (array $r, string $key) => [
                'key' => $key,
                'title' => $r[0],
                'description' => $r[1],
                'dated' => $r[2],
                'urls' => collect(ReportExporter::FORMATS)->mapWithKeys(fn (string $format) => [
                    $format => $this->areaRoute($request, 'reports.download', array_filter(['report' => $key, 'format' => $format, 'from' => $r[2] ? $filters['from'] : null, 'to' => $r[2] ? $filters['to'] : null])),
                ]),
            ])->values(),
        ]);
    }

    public function download(Request $request, string $report, string $format, ReportExporter $exporter): SymfonyResponse
    {
        abort_unless(isset(self::EXPORTS[$report]) && in_array($format, ReportExporter::FORMATS, true), 404);

        [$from, $to] = $this->period($request);
        [$title, , $dated] = self::EXPORTS[$report];
        $subtitle = $dated ? $from->format('d M Y').' to '.$to->format('d M Y') : 'As of '.now()->format('d M Y');
        $money = fn (int|string|null $minor) => round(((int) $minor) / 100, 2);
        $date = fn (mixed $value) => $value ? CarbonImmutable::parse($value)->format('Y-m-d') : '';

        [$headings, $rows] = match ($report) {
            'summary' => [
                ['Month', 'Income', 'Refunds', 'Expenses', 'Profit'],
                collect(FinancialReports::monthly($from, $to))->map(fn (array $m) => [$m['label'], $money($m['income']), $money($m['refunds']), $money($m['expenses']), $money($m['profit'])]),
            ],
            'transactions' => [
                ['Date', 'Direction', 'Number', 'Student', 'Student no.', 'Invoice', 'Method', 'Reference', 'Amount'],
                FinancialReports::transactions($from, $to)->lazy(500)->map(fn (stdClass $t) => [
                    $date($t->happened_at), $t->direction === 'in' ? 'Payment' : 'Refund', $t->number, $t->student, $t->student_number,
                    $t->invoice_number, $t->method, $t->transaction_reference, $money($t->direction === 'in' ? $t->amount : -1 * (int) $t->amount),
                ]),
            ],
            'invoices' => [
                ['Invoice', 'Raised', 'Student', 'Student no.', 'Fee type', 'Due date', 'Status', 'Amount', 'Paid', 'Refunded', 'Outstanding'],
                PaymentRequest::query()->with(['student.user', 'type'])->whereBetween('created_at', [$from, $to])->oldest('id')->lazy(500)->map(fn (PaymentRequest $r) => [
                    $r->invoice_number, $date($r->created_at), $r->student->user->name, $r->student->student_number, $r->type->name, $date($r->due_date),
                    $r->status->label(), $money($r->amount), $money($r->amount_paid), $money($r->amount_refunded), $money($r->status->isOpen() ? $r->outstanding() : 0),
                ]),
            ],
            'dues' => [
                ['Invoice', 'Student', 'Student no.', 'Counselor', 'Fee type', 'Due date', 'Days overdue', 'Status', 'Amount', 'Paid', 'Outstanding'],
                PaymentRequest::query()->with(['student.user', 'student.counselor', 'type'])->whereIn('status', FinancialReports::openStatuses())
                    ->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id')->lazy(500)->map(fn (PaymentRequest $r) => [
                        $r->invoice_number, $r->student->user->name, $r->student->student_number, $r->student->counselor?->name, $r->type->name, $date($r->due_date),
                        $r->due_date !== null && $r->due_date->isBefore(today()) ? (int) $r->due_date->diffInDays(today()) : 0,
                        $r->status === PaymentRequestStatus::Due ? 'Overdue' : $r->status->label(), $money($r->amount), $money($r->amount_paid), $money($r->outstanding()),
                    ]),
            ],
            'refunds' => [
                ['Refund', 'Requested', 'Student', 'Student no.', 'Invoice', 'Receipt', 'Status', 'Amount', 'Reason', 'Requested by', 'Approved by', 'Paid on', 'Paid by', 'Method', 'Reference'],
                Refund::query()->with(['student.user', 'request', 'payment', 'requester', 'approver', 'processor', 'method'])->whereBetween('created_at', [$from, $to])->oldest('id')->lazy(500)->map(fn (Refund $r) => [
                    $r->refund_number, $date($r->created_at), $r->student->user->name, $r->student->student_number, $r->request->invoice_number, $r->payment->receipt_number,
                    $r->status === RefundStatus::Processed ? 'Paid' : $r->status->label(), $money($r->amount), $r->reason, $r->requester?->name, $r->approver?->name,
                    $date($r->processed_at), $r->processor?->name, $r->method?->name, $r->transaction_reference,
                ]),
            ],
            'expenses' => [
                ['Date', 'Category', 'Title', 'Payee', 'Reference', 'Amount', 'Recorded by', 'Notes'],
                Expense::query()->with(['category', 'recorder'])->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->orderBy('spent_on')->orderBy('id')->lazy(500)->map(fn (Expense $e) => [
                    $date($e->spent_on), $e->category->name, $e->title, $e->payee, $e->reference, $money($e->amount), $e->recorder?->name, $e->notes,
                ]),
            ],
            'counselors' => [
                ['Counselor', 'Students', 'Invoiced', 'Collected', 'Refunded', 'Outstanding now'],
                FinancialReports::counselorCollection($from, $to)->map(fn (array $r) => [$r['counselor'], $r['students'], $money($r['invoiced']), $money($r['collected']), $money($r['refunded']), $money($r['outstanding'])]),
            ],
            default => [
                ['Student', 'Student no.', 'Counselor', 'Total fees', 'Paid', 'Refunded', 'Due'],
                FinancialReports::studentAccounts()->lazy(500)->map(fn (Student $s) => [
                    $s->user->name, $s->student_number, $s->counselor?->name, $money($s->getAttribute('fees')), $money($s->getAttribute('paid')), $money($s->getAttribute('refunded')), $money($s->getAttribute('due')),
                ]),
            ],
        };

        activity('finance')->causedBy($request->user())->withProperties(['report' => $report, 'format' => $format, 'from' => $from->toDateString(), 'to' => $to->toDateString()])->log('Exported report: '.$title);

        return $exporter->download($format, $title, $subtitle, $headings, $rows);
    }

    /**
     * The reporting period, defaulting to this month so far.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: array{from: string, to: string}}
     */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'])->startOfDay() : CarbonImmutable::now()->startOfMonth();
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'])->endOfDay() : CarbonImmutable::now()->endOfDay();

        if ($to->lessThan($from)) {
            $to = $from->endOfDay();
        }

        return [$from, $to, ['from' => $from->toDateString(), 'to' => $to->toDateString()]];
    }
}
