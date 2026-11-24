<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Support\Cms\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §22: global SEO, sitemap and robots.txt, on their own page (not part of Site settings). */
class SeoSettingsController extends Controller
{
    public function edit(SiteSettings $settings): Response
    {
        Gate::authorize('seo.manage');

        return Inertia::render('admin/seo/Settings', [
            'group' => collect(SiteSettings::groups()['seo'])->except('permission')->all(),
            'values' => $settings->forAdmin(false)['seo'] ?? [],
        ]);
    }

    public function update(Request $request, SiteSettings $settings): RedirectResponse
    {
        Gate::authorize('seo.manage');

        $data = $request->validate(SiteSettings::rules('seo'));
        $settings->save('seo', $data);

        activity('cms')->causedBy($request->user())->withProperties(['group' => 'seo', 'fields' => array_keys($data)])->log('SEO settings updated');

        $this->toast(['type' => 'success', 'message' => __('SEO settings saved.')]);

        return back();
    }
}
