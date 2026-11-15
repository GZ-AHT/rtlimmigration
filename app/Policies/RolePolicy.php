<?php

namespace App\Policies;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Spatie\Permission\Models\Role;

/** REQ §6 Role & Permission Management. */
class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('roles.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('roles.manage');
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->can('roles.manage') && self::isEditable($role);
    }

    public function delete(User $actor, Role $role): bool
    {
        return $actor->can('roles.manage') && self::isDeletable($role);
    }

    /*
     * Structural rules that must hold for everyone, Super Admin included.
     * Gate::before lets Super Admin skip policy methods, so controllers
     * call these directly as well.
     */

    /** Super Admin's access is implicit, not a permission list: nothing to edit. */
    public static function isEditable(Role $role): bool
    {
        return $role->name !== RoleEnum::SuperAdmin->value;
    }

    /** Built-in roles are required by the business rules (REQ §27). */
    public static function isDeletable(Role $role): bool
    {
        return RoleEnum::tryFrom($role->name) === null;
    }
}
