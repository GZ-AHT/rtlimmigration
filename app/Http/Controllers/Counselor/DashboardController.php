<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Models\LeadFollowUp;
use App\Models\Payment;
use App\Support\DashboardMetrics;
use App\Support\Presenters\PaymentPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §8.1 Counselor Dashboard overview. Visa figures arrive with Phase 6. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('counselor/Dashboard', [
            'stats' => DashboardMetrics::counselorStats($user),
            'followUps' => LeadFollowUp::query()
                ->whereNull('completed_at')
                ->where('due_at', '<=', now()->endOfDay())
                ->whereHas('lead', fn ($q) => $q->where('counselor_id', $user->id))
                ->with('lead:id,name,lead_number')
                ->orderBy('due_at')
                ->limit(6)
                ->get()
                ->map(fn (LeadFollowUp $f) => [
                    'id' => $f->id,
                    'due_at' => $f->due_at->toIso8601String(),
                    'overdue' => $f->due_at->isPast(),
                    'note' => $f->note,
                    'lead' => $f->lead->name,
                    'url' => route('counselor.leads.show', $f->lead_id, false),
                ]),
            'payments' => Payment::query()
                ->whereHas('student', fn ($q) => $q->where('counselor_id', $user->id))
                ->with(['request.type', 'student.user', 'method'])
                ->latest('updated_at')
                ->limit(6)
                ->get()
                ->map(fn (Payment $p) => [...PaymentPresenter::payment($p), 'url' => route('counselor.payment-requests.show', $p->payment_request_id, false)]),
        ]);
    }
}
