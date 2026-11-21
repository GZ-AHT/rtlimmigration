<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/** REQ §23.2: admin activity logs and user access (login) logs. IP addresses are only shown here. */
class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('audit.view');

        $f = $request->validate([
            'tab' => ['nullable', 'in:activity,logins'],
            'search' => ['nullable', 'string', 'max:100'],
            'log' => ['nullable', 'string', 'max:50'],
            'event' => ['nullable', 'in:login,logout,failed'],
        ]);
        $tab = $f['tab'] ?? 'activity';
        $search = trim((string) ($f['search'] ?? ''));
        $like = '%'.addcslashes($search, '%_\\').'%';

        $rows = $tab === 'logins'
            ? LoginHistory::query()->with('user:id,name')
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('email', 'like', $like)->orWhere('ip_address', 'like', $like)))
                ->when($f['event'] ?? null, fn ($q, $e) => $q->where('event', $e))
                ->latest('id')->paginate(25)->withQueryString()
                ->through(fn (LoginHistory $l) => ['id' => $l->id, 'when' => $l->created_at->toDateTimeString(), 'who' => $l->user->name ?? $l->email ?? 'Unknown', 'email' => $l->email, 'event' => $l->event, 'ip' => $l->ip_address, 'agent' => str((string) $l->user_agent)->limit(80)->toString()])
            : Activity::query()->with('causer:id,name')
                ->when($search !== '', fn ($q) => $q->where('description', 'like', $like))
                ->when($f['log'] ?? null, fn ($q, $l) => $q->where('log_name', $l))
                ->latest('id')->paginate(25)->withQueryString()
                ->through(fn (Activity $a) => ['id' => $a->id, 'when' => $a->created_at?->toDateTimeString(), 'who' => $a->causer instanceof User ? $a->causer->name : 'System', 'log' => $a->log_name, 'description' => $a->description, 'subject' => $a->subject_type ? class_basename($a->subject_type).' #'.$a->subject_id : null]);

        return Inertia::render('admin/system/Audit', [
            'tab' => $tab,
            'rows' => $rows,
            'filters' => ['search' => $search, 'log' => $f['log'] ?? '', 'event' => $f['event'] ?? ''],
            'logNames' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values(),
        ]);
    }
}
