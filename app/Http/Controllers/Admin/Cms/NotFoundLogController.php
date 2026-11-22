<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\NotFoundLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §22.3 "404 log": addresses visitors asked for that do not exist. */
class NotFoundLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('seo.manage');

        $search = trim((string) $request->query('search', ''));

        return Inertia::render('admin/seo/NotFound', [
            'logs' => NotFoundLog::query()
                ->when($search !== '', fn ($q) => $q->where('path', 'like', '%'.$search.'%'))
                ->orderByDesc('hits')->orderByDesc('last_seen_at')
                ->paginate(25)->withQueryString()
                ->through(fn (NotFoundLog $l) => [
                    'id' => $l->id,
                    'path' => $l->path,
                    'hits' => $l->hits,
                    'referrer' => $l->referrer,
                    'last_seen' => $l->last_seen_at->diffForHumans(),
                    'redirect_url' => route('admin.seo.redirects.index', ['from' => $l->path], absolute: false),
                ]),
            'filters' => ['search' => $search],
            'total' => NotFoundLog::query()->count(),
        ]);
    }

    public function destroy(Request $request, NotFoundLog $log): RedirectResponse
    {
        Gate::authorize('seo.manage');

        $log->delete();
        $this->toast(['type' => 'success', 'message' => __('Removed from the 404 log.')]);

        return back();
    }

    public function clear(Request $request): RedirectResponse
    {
        Gate::authorize('seo.manage');

        NotFoundLog::query()->delete();
        activity('cms')->causedBy($request->user())->log('404 log cleared');
        $this->toast(['type' => 'success', 'message' => __('404 log cleared.')]);

        return back();
    }
}
