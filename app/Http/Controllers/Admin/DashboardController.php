<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\StudentFile;
use App\Models\User;
use App\Support\DashboardMetrics;
use App\Support\Money;
use App\Support\Presenters\ActivityPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Super Admin overview (REQ §2). Each phase adds the KPIs for the module it
 * delivers; only figures backed by real data are sent.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                ['key' => 'students', 'label' => 'Total Students', 'value' => User::role(Role::Student->value)->count(), 'icon' => 'school'],
                ['key' => 'counselors', 'label' => 'Total Counselors', 'value' => User::role(Role::Counselor->value)->count(), 'icon' => 'support_agent'],
                ['key' => 'users', 'label' => 'Total Users', 'value' => User::count(), 'icon' => 'group'],
                ['key' => 'applications', 'label' => 'Total Applications', 'value' => StudentFile::count(), 'icon' => 'folder_shared', 'hint' => 'Student files'],
                // Financial figures only for those allowed to see them (REQ §26).
                ...($user->can('payments.view') ? [
                    ['key' => 'payments', 'label' => 'Total Payments', 'value' => Money::format((int) Payment::where('status', PaymentStatus::Confirmed->value)->sum('amount')), 'icon' => 'payments'],
                    ['key' => 'pending_bills', 'label' => 'Total Pending Bills', 'value' => PaymentRequest::whereIn('status', array_map(fn ($s) => $s->value, array_filter(PaymentRequestStatus::cases(), fn ($s) => $s->isOpen())))->count(), 'icon' => 'receipt_long'],
                ] : []),
            ],
            'monthlyRevenue' => $user->can('payments.view') ? DashboardMetrics::monthlyRevenue() : null,
            'recentRegistrations' => User::role(Role::Student->value)
                ->latest()
                ->limit(5)
                ->get(['id', 'name', 'email', 'created_at'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'registered_at' => $u->created_at?->toIso8601String(),
                    'url' => $user->can('users.view') ? route('admin.users.show', $u, absolute: false) : null,
                ]),
            // REQ §2 "Recent Activities": audit entries, for those allowed to see them.
            'recentActivity' => $user->can('audit.view')
                ? Activity::query()->with(['causer:id,name', 'subject'])->latest()->limit(8)->get()
                    ->map(fn (Activity $a) => ActivityPresenter::present($a))
                : null,
            // REQ §2 "Quick Actions", filtered by permission.
            'quickActions' => array_values(array_filter([
                $user->can('users.manage') ? ['label' => 'Add user', 'icon' => 'person_add', 'href' => route('admin.users.create', absolute: false)] : null,
                $user->can('users.view') ? ['label' => 'All users', 'icon' => 'group', 'href' => route('admin.users.index', absolute: false)] : null,
                $user->can('payments.verify') ? ['label' => 'Verify payments', 'icon' => 'pending', 'href' => route('admin.payments.pending', absolute: false)] : null,
                $user->can('roles.manage') ? ['label' => 'Roles & permissions', 'icon' => 'admin_panel_settings', 'href' => route('admin.roles.index', absolute: false)] : null,
                ['label' => 'Notifications', 'icon' => 'notifications', 'href' => route('notifications.index', absolute: false)],
            ])),
        ]);
    }
}
