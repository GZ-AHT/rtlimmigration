<?php

namespace App\Http\Controllers\Payments;

use App\Enums\GatewayTransactionStatus;
use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Models\GatewayTransaction;
use App\Models\Payment;
use App\Support\Gateways\GatewayManager;
use App\Support\Money;
use App\Support\Presenters\PaymentPresenter;
use App\Support\Reports\FinancialReports;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

/**
 * REQ §9.1 "Transaction History" and "Gateway Transactions". Finance areas
 * only (admin, accounts).
 */
class TransactionController extends Controller
{
    use ResolvesAreaRoutes;

    /** Money in and money out, in one list. */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'direction' => ['nullable', Rule::in(['in', 'out'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($filters['from']) ? CarbonImmutable::parse($filters['from'])->startOfDay() : null;
        $to = isset($filters['to']) ? CarbonImmutable::parse($filters['to'])->endOfDay() : null;

        $search = $filters['search'] ?? null;
        $in = (int) FinancialReports::transactions($from, $to, 'in', $search)->reorder()->sum('t.amount');
        $out = (int) FinancialReports::transactions($from, $to, 'out', $search)->reorder()->sum('t.amount');

        return Inertia::render('payments/Transactions', [
            'transactions' => FinancialReports::transactions($from, $to, $filters['direction'] ?? null, $filters['search'] ?? null)
                ->paginate(30)
                ->withQueryString()
                ->through(fn (stdClass $t) => [
                    'key' => $t->direction.'-'.$t->id,
                    'direction' => $t->direction,
                    'number' => $t->number,
                    'student' => $t->student,
                    'student_number' => $t->student_number,
                    'invoice_number' => $t->invoice_number,
                    'method' => $t->method,
                    'transaction_reference' => $t->transaction_reference,
                    'amount_label' => Money::format((int) $t->amount, (string) $t->currency),
                    'at' => CarbonImmutable::parse($t->happened_at)->toIso8601String(),
                    'url' => $this->areaRoute($request, $t->direction === 'in' ? 'payments.show' : 'refunds.show', $t->id),
                ]),
            'filters' => ['search' => $filters['search'] ?? '', 'direction' => $filters['direction'] ?? '', 'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
            'totals' => ['in' => Money::format($in), 'out' => Money::format($out), 'net' => Money::format(abs($in - $out)), 'net_negative' => $in - $out < 0],
            'exportUrl' => $request->user()->can('reports.export') && $this->areaHas($request, 'reports.download')
                ? $this->areaRoute($request, 'reports.download', ['report' => 'transactions', 'format' => 'xlsx', 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])
                : null,
        ]);
    }

    /** Every online attempt, including the ones that never became a payment. */
    public function gateway(Request $request, GatewayManager $gateways): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(GatewayTransactionStatus::class)],
        ]);

        return Inertia::render('payments/GatewayTransactions', [
            'transactions' => GatewayTransaction::query()
                ->with(['request', 'student.user', 'payment'])
                ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                    $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                    $q->where(fn (Builder $q) => $q->where('tran_id', 'like', $like)->orWhere('bank_tran_id', 'like', $like)
                        ->orWhereHas('request', fn (Builder $r) => $r->where('invoice_number', 'like', $like))
                        ->orWhereHas('student.user', fn (Builder $u) => $u->where('name', 'like', $like)));
                })
                ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
                ->latest()
                ->latest('id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (GatewayTransaction $t) => [
                    ...PaymentPresenter::gatewayTransaction($t),
                    'url' => $t->payment_id ? $this->areaRoute($request, 'payments.show', $t->payment_id) : $this->areaRoute($request, 'payment-requests.show', $t->payment_request_id),
                ]),
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''],
            'statuses' => GatewayTransactionStatus::options(),
            'gateway' => ($active = $gateways->active()) ? ['label' => $active->label(), 'sandbox' => (bool) config("payments.gateways.{$active->key()}.sandbox")] : null,
        ]);
    }
}
