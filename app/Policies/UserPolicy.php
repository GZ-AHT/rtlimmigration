<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * User management rules (REQ §6, §27).
 *
 * Super Admin passes Gate::before, so the "protect Super Admin accounts"
 * checks below only restrict other administrators. Self-protection and
 * last-Super-Admin checks live in methods Gate::before cannot bypass
 * (see canChangeAccess / isLastActiveSuperAdmin, used by the actions).
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can('users.manage') && $this->mayTouch($actor, $user);
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->can('users.manage') && $this->mayTouch($actor, $user);
    }

    public function restore(User $actor, User $user): bool
    {
        return $actor->can('users.manage') && $this->mayTouch($actor, $user);
    }

    /** Only Super Admins may manage Super Admin accounts. */
    private function mayTouch(User $actor, User $user): bool
    {
        return ! $user->hasRole(Role::SuperAdmin->value) || $actor->isSuperAdmin();
    }

    /**
     * Whether $actor may change $user's roles, status or existence. Nobody
     * may do this to their own account, to avoid locking themselves out.
     */
    public static function canChangeAccess(User $actor, User $user): bool
    {
        return $actor->isNot($user);
    }

    /** True when $user is the only active Super Admin left. */
    public static function isLastActiveSuperAdmin(User $user): bool
    {
        if (! $user->hasRole(Role::SuperAdmin->value) || ! $user->isActive()) {
            return false;
        }

        return User::role(Role::SuperAdmin->value)
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($user->getKey())
            ->doesntExist();
    }
}
