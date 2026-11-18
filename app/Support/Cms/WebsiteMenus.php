<?php

namespace App\Support\Cms;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The website's menus (REQ §21.3) as the header and footer render them.
 * The menu tree is cached for ten minutes, or until a menu or page changes
 * (scheduled pages appear within ten minutes of going live); who may see each
 * link (everyone, visitors, signed-in users, chosen roles) is applied per
 * request, so the cache holds no per-user data.
 */
class WebsiteMenus
{
    public const CACHE_KEY = 'website.menus';

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, list<array<string, mixed>>> location => items */
    public function for(?User $user): array
    {
        $roles = $user ? array_values($user->getRoleNames()->map(fn ($role) => (string) $role)->all()) : [];

        return array_map(fn (array $items) => $this->filter($items, $user, $roles), $this->all());
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function () {
            $out = array_fill_keys(array_keys(Menu::LOCATIONS), []);

            $menus = Menu::query()->with(['items' => fn ($q) => $q->where('is_active', true)->with('page:id,slug,system_key,status,published_at')])->get();

            foreach ($menus as $menu) {
                $items = $menu->items;
                $build = fn (?int $parent) => $items->where('parent_id', $parent)
                    ->map(fn (MenuItem $item) => $this->present($item))
                    ->filter(fn (array $item) => $item['href'] !== null)
                    ->values();

                $out[$menu->location] = array_values($build(null)
                    ->map(fn (array $item) => [...$item, 'children' => array_values($build($item['id'])->all())])
                    ->all());
            }

            return $out;
        });
    }

    /** @return array<string, mixed> */
    private function present(MenuItem $item): array
    {
        // A link to an unpublished page would only show a 404.
        $href = $item->link_type === 'page' && $item->page && ! $item->page->isLive() ? null : $item->href();

        return [
            'id' => $item->id,
            'label' => $item->label,
            'href' => $href,
            'new_tab' => $item->new_tab,
            'visibility' => $item->visibility,
            'roles' => $item->roles ?? [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $roles
     * @return list<array<string, mixed>>
     */
    private function filter(array $items, ?User $user, array $roles): array
    {
        $visible = array_filter($items, fn (array $item) => match ($item['visibility']) {
            'guests' => $user === null,
            'auth' => $user !== null,
            'roles' => array_intersect($roles, $item['roles']) !== [],
            default => true,
        });

        return array_values(array_map(fn (array $item) => [
            'label' => $item['label'],
            'href' => $item['href'],
            'new_tab' => $item['new_tab'],
            'children' => isset($item['children']) ? $this->filter($item['children'], $user, $roles) : [],
        ], $visible));
    }
}
