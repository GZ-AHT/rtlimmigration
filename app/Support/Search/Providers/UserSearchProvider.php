<?php

namespace App\Support\Search\Providers;

use App\Models\User;
use App\Support\Presenters\UserPresenter;
use App\Support\Search\SearchProvider;

class UserSearchProvider implements SearchProvider
{
    public function label(): string
    {
        return __('Users');
    }

    public function authorize(User $user): bool
    {
        return $user->can('users.view');
    }

    public function search(string $term, User $user, int $limit): array
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return array_values(User::query()
            ->with('roles:id,name')
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $u) => [
                'title' => $u->name,
                'subtitle' => $u->email.(($role = $u->getRoleNames()->first()) ? ' · '.UserPresenter::roleLabel((string) $role) : ''),
                'url' => route('admin.users.show', $u, absolute: false),
                'icon' => 'person',
            ])
            ->all());
    }
}
