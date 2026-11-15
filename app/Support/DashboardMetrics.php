<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Enums\FileStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentRequestStatus;
use App\Enums\PaymentStatus;
use App\Models\Document;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\Student;
use App\Models\StudentFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Figures for the dashboards (REQ §2, §8.1, §9.1, §10.1). Everything is
 * read from the same tables the screens use (REQ §26 single source of
 * truth); visa figures arrive with Phase 6.
 */
class DashboardMetrics
{
    /** @return list<array{key: string, label: string, value: int|string, icon: string, hint?: string}> */
    public static function counselorStats(User $user): array
    {
        $closed = array_values(array_map(fn ($s) => $s->value, array_filter(LeadStatus::cases(), fn ($s) => $s->isClosed())));
        $mine = fn (Builder $q) => $q->where('counselor_id', $user->id);

        return [
            ['key' => 'leads', 'label' => 'Assigned leads', 'value' => Lead::where('counselor_id', $user->id)->whereNotIn('status', $closed)->count(), 'icon' => 'person_search'],
            ['key' => 'students', 'label' => 'Total students', 'value' => Student::where('counselor_id', $user->id)->count(), 'icon' => 'school'],
            ['key' => 'new_students', 'label' => 'New students', 'value' => Student::where('counselor_id', $user->id)->where('created_at', '>=', now()->subDays(30))->count(), 'icon' => 'person_add', 'hint' => 'Last 30 days'],
            ['key' => 'files', 'label' => 'Active files', 'value' => StudentFile::whereHas('student', $mine)->whereIn('status', [FileStatus::Open->value, FileStatus::OnHold->value])->count(), 'icon' => 'folder_shared'],
            ['key' => 'documents', 'label' => 'Pending documents', 'value' => Document::where('is_current', true)->where('status', DocumentStatus::Pending->value)->whereHas('student', $mine)->count(), 'icon' => 'description'],
            ['key' => 'payment_pending', 'label' => 'Payment pending', 'value' => PaymentRequest::whereHas('student', $mine)->whereIn('status', [PaymentRequestStatus::Unpaid->value, PaymentRequestStatus::Pending->value, PaymentRequestStatus::Partial->value, PaymentRequestStatus::Due->value, PaymentRequestStatus::Rejected->value])->count(), 'icon' => 'pending_actions'],
            ['key' => 'payment_confirmed', 'label' => 'Payments confirmed', 'value' => Payment::whereHas('student', $mine)->where('status', PaymentStatus::Confirmed->value)->where('confirmed_at', '>=', now()->startOfMonth())->count(), 'icon' => 'task_alt', 'hint' => 'This month'],
            ['key' => 'follow_ups', 'label' => 'Follow-up tasks', 'value' => LeadFollowUp::whereNull('completed_at')->where('due_at', '<=', now()->endOfDay())->whereHas('lead', $mine)->count(), 'icon' => 'event_repeat', 'hint' => 'Due today or overdue'],
        ];
    }

    /** @return list<array{key: string, label: string, value: int|string, icon: string, hint?: string}> */
    public static function accountsStats(): array
    {
        $confirmed = Payment::where('status', PaymentStatus::Confirmed->value);
        $openStatuses = array_values(array_map(fn ($s) => $s->value, array_filter(PaymentRequestStatus::cases(), fn ($s) => $s->isOpen())));
        $today = (clone $confirmed)->whereDate('confirmed_at', today());

        return [
            ['key' => 'revenue', 'label' => 'Total revenue', 'value' => Money::format((int) (clone $confirmed)->sum('amount')), 'icon' => 'payments'],
            ['key' => 'received', 'label' => 'Received payments', 'value' => (clone $confirmed)->count(), 'icon' => 'task_alt'],
            ['key' => 'due', 'label' => 'Total due', 'value' => Money::format((int) PaymentRequest::whereIn('status', $openStatuses)->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as o')->value('o')), 'icon' => 'pending_actions', 'hint' => PaymentRequest::where('status', PaymentRequestStatus::Due->value)->count().' overdue'],
            ['key' => 'pending', 'label' => 'Pending verification', 'value' => Payment::whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Verified->value])->count(), 'icon' => 'pending'],
            ['key' => 'today', 'label' => "Today's transactions", 'value' => (clone $today)->count(), 'icon' => 'today', 'hint' => Money::format((int) (clone $today)->sum('amount'))],
        ];
    }

    /**
     * Confirmed revenue per month for the last N months (REQ §2, §9.1
     * "Monthly Revenue").
     *
     * @return list<array{label: string, value: int, display: string}>
     */
    public static function monthlyRevenue(int $months = 12): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $rows = Payment::where('status', PaymentStatus::Confirmed->value)
            ->where('confirmed_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(confirmed_at, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $series = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->addMonths($i);
            $value = (int) ($rows[$month->format('Y-m')] ?? 0);
            $series[] = ['label' => $month->format('M'), 'value' => $value, 'display' => Money::format($value)];
        }

        return $series;
    }

    /**
     * Confirmed totals per payment method (REQ §9.1 "Payment Method Statistics").
     *
     * @return list<array{label: string, count: int, total: string, share: int}>
     */
    public static function methodStats(): array
    {
        $rows = Payment::where('payments.status', PaymentStatus::Confirmed->value)
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->selectRaw('payment_methods.name as name, COUNT(*) as c, SUM(payments.amount) as total')
            ->groupBy('payment_methods.name')
            ->orderByDesc('total')
            ->get();

        $grand = max(1, (int) $rows->sum('total'));

        return array_values($rows->map(fn ($r) => [
            'label' => (string) $r->getAttribute('name'),
            'count' => (int) $r->getAttribute('c'),
            'total' => Money::format((int) $r->getAttribute('total')),
            'share' => (int) round(((int) $r->getAttribute('total')) / $grand * 100),
        ])->all());
    }
}
