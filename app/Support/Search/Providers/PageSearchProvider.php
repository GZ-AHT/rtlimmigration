<?php

namespace App\Support\Search\Providers;

use App\Models\User;
use App\Support\Navigation;
use App\Support\Search\SearchProvider;

/** Finds screens in the user's own sidebar by name ("roles", "users"...). */
class PageSearchProvider implements SearchProvider
{
    public function __construct(private Navigation $navigation) {}

    public function label(): string
    {
        return __('Pages');
    }

    public function authorize(User $user): bool
    {
        return true;
    }

    public function search(string $term, User $user, int $limit): array
    {
        $area = $user->homeArea();

        if ($area === null) {
            return [];
        }

        $needle = mb_strtolower($term);
        $results = [];

        foreach ($this->navigation->for($area, $user) as $group) {
            foreach ($group['items'] as $item) {
                foreach ([$item, ...($item['children'] ?? [])] as $entry) {
                    if ($entry['href'] !== null && str_contains(mb_strtolower($entry['title']), $needle)) {
                        $results[$entry['href']] = [
                            'title' => $entry['title'],
                            'subtitle' => $group['label'],
                            'url' => $entry['href'],
                            'icon' => $entry['icon'] ?? $item['icon'],
                        ];
                    }
                }
            }
        }

        return array_slice(array_values($results), 0, $limit);
    }
}
