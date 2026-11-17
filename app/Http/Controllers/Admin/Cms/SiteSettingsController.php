<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Support\Cms\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §21.1, §21.2, §21.4, §21.5, §21.6: website settings in tabs. */
class SiteSettingsController extends Controller
{
    public function edit(Request $request, SiteSettings $settings): Response
    {
        Gate::authorize('cms.manage');

        $withCode = $request->user()->can('cms.custom-code');
        $groups = collect(SiteSettings::groups())
            ->reject(fn ($g, $key) => $key === 'code' && ! $withCode)
            ->map(fn ($g) => collect($g)->except('permission')->all());

        return Inertia::render('admin/cms/SiteSettings', [
            'groups' => $groups,
            'values' => $settings->forAdmin($withCode),
            'tab' => (string) ($request->validate(['tab' => ['nullable', 'string', 'max:20']])['tab'] ?? 'general'),
        ]);
    }

    public function update(Request $request, SiteSettings $settings, string $group): RedirectResponse
    {
        Gate::authorize('cms.manage');
        abort_unless(array_key_exists($group, SiteSettings::groups()), 404);

        if ($group === 'code') {
            Gate::authorize('cms.custom-code');
        }

        $data = $request->validate(SiteSettings::rules($group));
        $settings->save($group, $data);

        activity($group === 'code' ? 'security' : 'cms')->causedBy($request->user())->withProperties(['group' => $group, 'fields' => array_keys($data)])
            ->log($group === 'code' ? 'Website custom code changed' : 'Website settings updated');

        $this->toast(['type' => 'success', 'message' => __(':group settings saved.', ['group' => SiteSettings::groups()[$group]['label']])]);

        return back();
    }
}
