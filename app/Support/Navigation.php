<?php

namespace App\Support;

use App\Enums\Area;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Builds the permission-filtered sidebar for an area (REQ §25: "The sidebar
 * must be dynamic and permission-based"). This only decides what is shown;
 * every route still enforces its own authorization.
 *
 * An item may have `children` (a sub-menu, like admin-demo.html's .subnav).
 * A parent is shown when at least one of its children is.
 */
class Navigation
{
    /**
     * @return list<array{label: string, items: list<array<string, mixed>>}>
     */
    public function for(Area $area, User $user): array
    {
        $groups = [];

        foreach (config("navigation.areas.{$area->value}", []) as $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                if (isset($item['children'])) {
                    $children = array_values(array_filter(array_map(
                        fn (array $child) => $this->leaf($child, $user),
                        $item['children'],
                    )));

                    if ($children === []) {
                        continue;
                    }

                    $items[] = [
                        'title' => $item['title'],
                        'icon' => $item['icon'],
                        'href' => null,
                        'phase' => min(array_column($children, 'phase')),
                        'planned' => collect($children)->every(fn ($c) => $c['planned']),
                        'children' => $children,
                    ];

                    continue;
                }

                if ($leaf = $this->leaf($item, $user)) {
                    $items[] = $leaf + ['children' => []];
                }
            }

            if ($items !== []) {
                $groups[] = ['label' => $group['label'], 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{title: string, icon: string|null, href: string|null, phase: int, planned: bool}|null
     */
    private function leaf(array $item, User $user): ?array
    {
        if (($item['permission'] ?? null) !== null && ! $user->can($item['permission'])) {
            return null;
        }

        $built = ($item['route'] ?? null) !== null && Route::has($item['route']);

        if (! $built && ! config('navigation.show_planned')) {
            return null;
        }

        return [
            'title' => $item['title'],
            'icon' => $item['icon'] ?? null,
            'href' => $built ? route($item['route'], absolute: false) : null,
            'phase' => (int) $item['phase'],
            'planned' => ! $built,
        ];
    }
}
