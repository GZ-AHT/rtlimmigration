<?php

namespace App\Http\Middleware;

use App\Enums\Area;
use App\Http\Controllers\NotificationController;
use App\Models\User;
use App\Support\Chat\ChatSecuritySettings;
use App\Support\Chat\ChatSettings;
use App\Support\Navigation;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        $area = $user ? $this->currentArea($request, $user) : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'roles' => fn () => $user?->getRoleNames()->values() ?? [],
                // The UI uses these to hide controls only; the server
                // re-checks every action (REQ §31.5).
                'isSuperAdmin' => fn () => (bool) $user?->isSuperAdmin(),
                'permissions' => fn () => $user?->getAllPermissions()->pluck('name')->values() ?? [],
            ],
            'area' => $area ? ['key' => $area->value, 'label' => $area->label()] : null,
            'navigation' => fn () => $user && $area ? app(Navigation::class)->for($area, $user) : [],
            'notifications' => fn () => $user ? [
                'unread' => $user->unreadNotifications()->count(),
                'recent' => $user->notifications()->limit(5)->get()->map(NotificationController::present(...)),
            ] : null,
            // REQ §18: the Tawk.to widget for visitors and students; null for staff, or while Tawk.to is switched off.
            'chat' => fn () => app(ChatSettings::class)->widgetFor($user),
            // REQ §11 F: show the website chat button to visitors and students (staff answer from the panel).
            'guestChat' => fn () => app(ChatSecuritySettings::class)->guestChatEnabled() && ($user === null || $user->homeArea() === Area::Portal),
            'site' => fn () => app(SettingsRepository::class)->public(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The area whose sidebar to show: the one the route belongs to, or the
     * user's home area for shared screens such as profile settings.
     */
    private function currentArea(Request $request, User $user): ?Area
    {
        $prefix = strstr((string) $request->route()?->getName(), '.', true);

        return Area::tryFrom((string) $prefix) ?? $user->homeArea();
    }
}
