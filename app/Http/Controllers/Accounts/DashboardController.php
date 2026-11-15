<?php

namespace App\Http\Controllers\Accounts;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\DashboardMetrics;
use App\Support\Presenters\PaymentPresenter;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §9.1 Accounts Dashboard overview. Refund totals arrive with Phase 5. */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('accounts/Dashboard', [
            'stats' => DashboardMetrics::accountsStats(),
            'monthly' => DashboardMetrics::monthlyRevenue(),
            'methods' => DashboardMetrics::methodStats(),
            'queue' => Payment::query()
                ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Verified->value])
                ->with(['request.type', 'student.user', 'method'])
                ->oldest()
                ->limit(6)
                ->get()
                ->map(fn (Payment $p) => [...PaymentPresenter::payment($p), 'url' => route('accounts.payments.show', $p, false)]),
        ]);
    }
}
