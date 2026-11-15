<?php

namespace App\Support\Search;

use App\Models\User;

class GlobalSearch
{
    /** @param  list<SearchProvider>  $providers */
    public function __construct(private array $providers) {}

    /**
     * @return list<array{group: string, results: list<array{title: string, subtitle: string|null, url: string, icon: string}>}>
     */
    public function search(string $term, User $user, int $perGroup = 5): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $groups = [];

        foreach ($this->providers as $provider) {
            if (! $provider->authorize($user)) {
                continue;
            }

            $results = $provider->search($term, $user, $perGroup);

            if ($results !== []) {
                $groups[] = ['group' => $provider->label(), 'results' => $results];
            }
        }

        return $groups;
    }
}
