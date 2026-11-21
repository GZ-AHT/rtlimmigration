<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceMaintenance;
use App\Support\System\LogReader;
use App\Support\System\SystemInfo;
use App\Support\System\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §23.1–23.3: system overview, cache tools, logs, maintenance, security and integration settings. */
class SystemController extends Controller
{
    /** tab => permission */
    private const TABS = [
        'overview' => 'settings.manage', 'maintenance' => 'settings.manage', 'cache' => 'settings.manage', 'logs' => 'settings.manage',
        'security' => 'security.manage', 'integrations' => 'integrations.manage',
    ];

    private const CACHES = ['application' => 'cache:clear', 'route' => 'route:clear', 'view' => 'view:clear', 'config' => 'config:clear'];

    public function show(Request $request, SystemSettings $settings): Response
    {
        $user = $request->user();
        $allowed = array_keys(array_filter(self::TABS, fn (string $permission) => $user->can($permission)));
        abort_if($allowed === [], 403);

        $query = $request->validate(['tab' => ['nullable', 'string'], 'level' => ['nullable', 'in:'.implode(',', LogReader::LEVELS)]]);
        $tab = in_array($query['tab'] ?? '', $allowed, true) ? $query['tab'] : $allowed[0];
        $groups = collect(SystemSettings::groups())->only($allowed)->map(fn ($g) => collect($g)->except('permission')->all());

        return Inertia::render('admin/system/Settings', [
            'tab' => $tab,
            'tabs' => array_map(fn (string $t) => ['key' => $t, 'label' => ucfirst($t)], $allowed),
            'groups' => $groups,
            ...$settings->forAdmin($allowed),
            'overview' => $tab === 'overview' ? SystemInfo::overview() : null,
            'storage' => $tab === 'overview' ? SystemInfo::storage() : null,
            'logs' => $tab === 'logs' ? [...LogReader::read($query['level'] ?? null), 'level' => $query['level'] ?? ''] : null,
            'levels' => LogReader::LEVELS,
            'caches' => array_keys(self::CACHES),
            'maintenanceActive' => EnforceMaintenance::active(),
        ]);
    }

    public function update(Request $request, SystemSettings $settings, string $group): RedirectResponse
    {
        abort_unless(array_key_exists($group, SystemSettings::groups()), 404);
        Gate::authorize(SystemSettings::groups()[$group]['permission']);

        $data = $request->validate(SystemSettings::rules($group));
        $settings->save($group, $data);

        // Never log the values: they include secrets and IP lists.
        activity('security')->causedBy($request->user())->withProperties(['group' => $group, 'fields' => array_keys($data)])->log(SystemSettings::groups()[$group]['label'].' settings changed');
        $this->toast(['type' => 'success', 'message' => __(':group settings saved.', ['group' => SystemSettings::groups()[$group]['label']])]);

        return back();
    }

    public function clearCache(Request $request, string $type): RedirectResponse
    {
        Gate::authorize('settings.manage');
        abort_unless($type === 'all' || array_key_exists($type, self::CACHES), 404);

        foreach ($type === 'all' ? self::CACHES : [$type => self::CACHES[$type]] as $command) {
            Artisan::call($command);
        }

        Cache::forget('system.storage-usage');
        activity('system')->causedBy($request->user())->withProperties(['cache' => $type])->log('Cache cleared');
        $this->toast(['type' => 'success', 'message' => __('Cache cleared.')]);

        return back();
    }
}
