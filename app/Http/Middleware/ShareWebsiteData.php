<?php

namespace App\Http\Middleware;

use App\Models\Media;
use App\Models\User;
use App\Support\Cms\SectionHydrator;
use App\Support\Cms\WebsiteMenus;
use App\Support\Settings\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the header and footer menus and the logo images with public
 * website pages only, so admin and portal screens don't carry them.
 */
class ShareWebsiteData
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        Inertia::share('menus', fn () => app(WebsiteMenus::class)->for($user));
        Inertia::share('siteMedia', fn () => self::media());

        return $next($request);
    }

    /** @return array<string, mixed> Logo and icon images from Site settings → Logo & branding. */
    public static function media(): array
    {
        $settings = app(SettingsRepository::class);
        $keys = ['logo' => 'branding.logo', 'logo_light' => 'branding.logo_light', 'logo_mobile' => 'branding.logo_mobile', 'favicon' => 'branding.favicon', 'apple_icon' => 'branding.apple_icon'];
        $ids = array_map(fn (string $key) => $settings->get($key), $keys);
        $payment = array_map('intval', (array) $settings->get('footer.payment_logos', []));
        $media = Media::query()->whereKey([...array_filter($ids, 'is_numeric'), ...$payment])->get()->keyBy('id');

        return [
            ...array_map(fn ($id) => is_numeric($id) ? SectionHydrator::image($media->get((int) $id)) : null, $ids),
            'payment_logos' => array_values(array_filter(array_map(fn (int $id) => SectionHydrator::image($media->get($id)), $payment))),
        ];
    }
}
