<?php

namespace App\Support\Search;

use App\Models\User;

/**
 * One searchable module in the global search (REQ §1). Each later phase
 * registers its own provider (leads, students, files, invoices...).
 */
interface SearchProvider
{
    /** Group heading shown above this provider's results. */
    public function label(): string;

    /** Whether the user may search this module at all. */
    public function authorize(User $user): bool;

    /**
     * @return list<array{title: string, subtitle: string|null, url: string, icon: string}>
     */
    public function search(string $term, User $user, int $limit): array;
}
