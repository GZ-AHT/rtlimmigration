<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\BrokenLink;
use App\Support\Seo\LinkScanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §22.3: broken-link monitor. A weekly scan fills it; "Scan now" runs one after the response. */
class BrokenLinkController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('seo.manage');

        $links = BrokenLink::query()->orderBy('source_label')->orderBy('target_url')->get();

        return Inertia::render('admin/seo/BrokenLinks', [
            'links' => $links->map(fn (BrokenLink $l) => [
                'id' => $l->id, 'source' => $l->source_label, 'source_url' => $l->source_url, 'target' => $l->target_url,
                'internal' => str_starts_with($l->target_url, '/'), 'status' => $l->status_code, 'error' => $l->error,
            ])->values(),
            'last_checked' => $links->max('checked_at')?->diffForHumans(),
        ]);
    }

    public function scan(): RedirectResponse
    {
        Gate::authorize('seo.manage');

        dispatch(fn () => app(LinkScanner::class)->run())->afterResponse();
        $this->toast(['type' => 'success', 'message' => __('Scan started. Refresh in a minute to see the results.')]);

        return back();
    }
}
