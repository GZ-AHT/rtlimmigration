<?php

namespace App\Support\Reports;

use App\Enums\PaymentRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\Refund;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finance figures (REQ §9, §9.1, §24). Everything is read from the ledger
 * tables the screens use, and in minor units:
 *
 *   income    payments confirmed in the period (by confirmation time)
 *   refunds   refunds paid out in the period
 *   expenses  expense records dated in the period
 *
 * Callers format for display or export.
 */
class FinancialReports
{
    /**
     * @return array{income: int, payments: int, refunds: int, refund_count: int, net_income: int, expenses: int, profit: int, invoiced: int, outstanding: int, overdue: int}
     */
    public static function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $income = self::income($from, $to);
        $refunds = self::refunds($from, $to);
        $open = self::openStatuses();

        $incomeTotal = (int) (clone $income)->sum('amount');
        $refundTotal = (int) (clone $refunds)->sum('amount');
        $expenses = (int) Expense::whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->sum('amount');

        return [
            'income' => $incomeTotal,
            'payments' => (clone $income)->count(),
            'refunds' => $refundTotal,
            'refund_count' => (clone $refunds)->count(),
            'net_income' => $incomeTotal - $refundTotal,
            'expenses' => $expenses,
            'profit' => $incomeTotal - $refundTotal - $expenses,
            'invoiced' => (int) PaymentRequest::whereBetween('created_at', [$from, $to])->where('status', '!=', PaymentRequestStatus::Cancelled->value)->sum('amount'),
            // Dues are a position, not a flow: what is owed right now.
            'outstanding' => (int) PaymentRequest::whereIn('status', $open)->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as o')->value('o'),
            'overdue' => (int) PaymentRequest::where('status', PaymentRequestStatus::Due->value)->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as o')->value('o'),
        ];
    }

    /** @return Collection<int, array{label: string, count: int, total: int}> */
    public static function incomeByType(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return self::income($from, $to)
            ->join('payment_requests', 'payment_requests.id', '=', 'payments.payment_request_id')
            ->join('payment_types', 'payment_types.id', '=', 'payment_requests.payment_type_id')
            ->selectRaw('payment_types.name as label, COUNT(*) as c, SUM(payments.amount) as total')
            ->groupBy('payment_types.name')
            ->orderByDesc('total')
            ->toBase()
            ->get()
            ->map(fn (object $r) => ['label' => (string) $r->label, 'count' => (int) $r->c, 'total' => (int) $r->total]);
    }

    /** @return Collection<int, array{label: string, count: int, total: int}> */
    public static function incomeByMethod(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return self::income($from, $to)
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->selectRaw('payment_methods.name as label, COUNT(*) as c, SUM(payments.amount) as total')
            ->groupBy('payment_methods.name')
            ->orderByDesc('total')
            ->toBase()
            ->get()
            ->map(fn (object $r) => ['label' => (string) $r->label, 'count' => (int) $r->c, 'total' => (int) $r->total]);
    }

    /** @return Collection<int, array{label: string, count: int, total: int}> */
    public static function expensesByCategory(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Expense::query()
            ->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw('expense_categories.name as label, COUNT(*) as c, SUM(expenses.amount) as total')
            ->groupBy('expense_categories.name')
            ->orderByDesc('total')
            ->toBase()
            ->get()
            ->map(fn (object $r) => ['label' => (string) $r->label, 'count' => (int) $r->c, 'total' => (int) $r->total]);
    }

    /**
     * Income, refunds and expenses for each calendar month the period
     * touches (REQ §9 "Monthly / Yearly Reports").
     *
     * @return list<array{month: string, label: string, income: int, refunds: int, expenses: int, profit: int}>
     */
    public static function monthly(CarbonInterface $from, CarbonInterface $to): array
    {
        $income = self::income($from, $to)->selectRaw("DATE_FORMAT(confirmed_at, '%Y-%m') as ym, SUM(amount) as total")->groupBy('ym')->toBase()->pluck('total', 'ym');
        $refunds = self::refunds($from, $to)->selectRaw("DATE_FORMAT(processed_at, '%Y-%m') as ym, SUM(amount) as total")->groupBy('ym')->toBase()->pluck('total', 'ym');
        $expenses = Expense::whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->selectRaw("DATE_FORMAT(spent_on, '%Y-%m') as ym, SUM(amount) as total")->groupBy('ym')->toBase()->pluck('total', 'ym');

        $rows = [];
        $month = CarbonImmutable::parse($from)->startOfMonth();
        $last = CarbonImmutable::parse($to)->startOfMonth();

        // Capped so a careless date range cannot build an enormous table.
        for ($i = 0; $month->lessThanOrEqualTo($last) && $i < 60; $i++, $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $in = (int) ($income[$key] ?? 0);
            $out = (int) ($refunds[$key] ?? 0);
            $spent = (int) ($expenses[$key] ?? 0);
            $rows[] = ['month' => $key, 'label' => $month->format('M Y'), 'income' => $in, 'refunds' => $out, 'expenses' => $spent, 'profit' => $in - $out - $spent];
        }

        return $rows;
    }

    /**
     * REQ §9.1 "Counselor-wise Collection": money by the counselor a
     * student is assigned to today. Students with no counselor are grouped
     * as "Unassigned".
     *
     * @return Collection<int, array{counselor: string, students: int, invoiced: int, collected: int, refunded: int, outstanding: int}>
     */
    public static function counselorCollection(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $byCounselor = fn (QueryBuilder $q, string $table) => $q->join('students', 'students.id', '=', $table.'.student_id')
            ->groupBy('students.counselor_id')
            ->pluck('total', 'counselor_id');

        $collected = $byCounselor(self::income($from, $to)->toBase()->selectRaw('students.counselor_id as counselor_id, SUM(payments.amount) as total'), 'payments');
        $refunded = $byCounselor(self::refunds($from, $to)->toBase()->selectRaw('students.counselor_id as counselor_id, SUM(refunds.amount) as total'), 'refunds');
        $invoiced = $byCounselor(PaymentRequest::whereBetween('payment_requests.created_at', [$from, $to])->where('payment_requests.status', '!=', PaymentRequestStatus::Cancelled->value)->toBase()
            ->selectRaw('students.counselor_id as counselor_id, SUM(payment_requests.amount) as total'), 'payment_requests');
        $outstanding = $byCounselor(PaymentRequest::whereIn('payment_requests.status', self::openStatuses())->toBase()
            ->selectRaw('students.counselor_id as counselor_id, SUM(payment_requests.amount - payment_requests.amount_paid) as total'), 'payment_requests');

        $students = DB::table('students')->selectRaw('counselor_id, COUNT(*) as c')->groupBy('counselor_id')->pluck('c', 'counselor_id');
        $names = DB::table('users')->whereIn('id', $students->keys()->filter()->all())->pluck('name', 'id');

        // pluck() keys a NULL counselor as "", in every one of these lists.
        return $students->keys()
            ->map(function (int|string $id) use ($names, $students, $collected, $refunded, $invoiced, $outstanding) {
                $key = $id;

                return [
                    'counselor' => $id ? (string) ($names[$id] ?? 'Former counselor') : 'Unassigned',
                    'students' => (int) ($students[$key] ?? 0),
                    'invoiced' => (int) ($invoiced[$key] ?? 0),
                    'collected' => (int) ($collected[$key] ?? 0),
                    'refunded' => (int) ($refunded[$key] ?? 0),
                    'outstanding' => (int) ($outstanding[$key] ?? 0),
                ];
            })
            ->sortByDesc('collected')
            ->values();
    }

    /**
     * REQ §9.1 "Student-wise Accounts": each student's fees, payments,
     * refunds and balance, largest balance first.
     *
     * @return Builder<Student>
     */
    public static function studentAccounts(?string $search = null, bool $dueOnly = false): Builder
    {
        $billed = fn (Builder $q) => $q->where('status', '!=', PaymentRequestStatus::Cancelled->value);

        return Student::query()
            ->with(['user', 'counselor'])
            ->withSum(['paymentRequests as fees' => $billed], 'amount')
            ->withSum(['paymentRequests as paid' => $billed], 'amount_paid')
            ->withSum(['paymentRequests as refunded' => $billed], 'amount_refunded')
            ->withSum(['paymentRequests as due' => fn (Builder $q) => $q->whereIn('status', self::openStatuses())], DB::raw('amount - amount_paid'))
            ->when($search, function (Builder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where(fn (Builder $q) => $q->where('student_number', 'like', $like)->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)));
            })
            ->when($dueOnly, fn (Builder $q) => $q->whereHas('paymentRequests', fn (Builder $r) => $r->whereIn('status', self::openStatuses())))
            ->whereHas('paymentRequests')
            ->orderByRaw('COALESCE(due, 0) DESC')
            ->orderBy('id');
    }

    /**
     * REQ §9.1 "Transaction History": money in (confirmed payments) and
     * money out (refunds paid), newest first.
     *
     * @param  'in'|'out'|null  $direction
     */
    public static function transactions(?CarbonInterface $from, ?CarbonInterface $to, ?string $direction = null, ?string $search = null): QueryBuilder
    {
        $in = DB::table('payments')
            ->where('payments.status', PaymentStatus::Confirmed->value)
            ->selectRaw("'in' as direction, payments.id as id, payments.student_id, payments.payment_request_id, payments.amount, payments.currency, payments.confirmed_at as happened_at, payments.receipt_number as number, payments.payment_method_id, payments.transaction_reference");

        $out = DB::table('refunds')
            ->where('refunds.status', RefundStatus::Processed->value)
            ->selectRaw("'out' as direction, refunds.id as id, refunds.student_id, refunds.payment_request_id, refunds.amount, refunds.currency, refunds.processed_at as happened_at, refunds.refund_number as number, refunds.payment_method_id, refunds.transaction_reference");

        $union = match ($direction) {
            'in' => $in,
            'out' => $out,
            default => $in->unionAll($out),
        };

        return DB::query()
            ->fromSub($union, 't')
            ->join('students', 'students.id', '=', 't.student_id')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->join('payment_requests', 'payment_requests.id', '=', 't.payment_request_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 't.payment_method_id')
            ->select(['t.*', 'users.name as student', 'students.student_number', 'payment_requests.invoice_number', 'payment_methods.name as method'])
            ->when($from, fn (QueryBuilder $q) => $q->where('t.happened_at', '>=', $from))
            ->when($to, fn (QueryBuilder $q) => $q->where('t.happened_at', '<=', $to))
            ->when($search, function (QueryBuilder $q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where(fn (QueryBuilder $q) => $q->where('t.number', 'like', $like)
                    ->orWhere('t.transaction_reference', 'like', $like)
                    ->orWhere('payment_requests.invoice_number', 'like', $like)
                    ->orWhere('users.name', 'like', $like)
                    ->orWhere('students.student_number', 'like', $like));
            })
            ->orderByDesc('t.happened_at')
            ->orderByDesc('t.id');
    }

    /** @return list<string> */
    public static function openStatuses(): array
    {
        return array_values(array_map(fn (PaymentRequestStatus $s) => $s->value, array_filter(PaymentRequestStatus::cases(), fn (PaymentRequestStatus $s) => $s->isOpen())));
    }

    /** @return Builder<Payment> */
    private static function income(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Payment::query()->where('payments.status', PaymentStatus::Confirmed->value)->whereBetween('payments.confirmed_at', [$from, $to]);
    }

    /** @return Builder<Refund> */
    private static function refunds(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Refund::query()->where('refunds.status', RefundStatus::Processed->value)->whereBetween('refunds.processed_at', [$from, $to]);
    }
}
