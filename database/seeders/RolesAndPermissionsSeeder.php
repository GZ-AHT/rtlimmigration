<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use UnexpectedValueException;

/**
 * Idempotent: safe to run on every deploy. Creates missing permissions and
 * roles and adds missing default grants, without removing anything an
 * administrator has changed.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        foreach (config('rbac.permissions') as $module => $permissions) {
            foreach (array_keys($permissions) as $name) {
                if (! is_string($name)) {
                    throw new UnexpectedValueException("config/rbac.php [{$module}] must map permission names to descriptions.");
                }

                Permission::findOrCreate($name, $guard);
            }
        }

        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::findOrCreate($roleEnum->value, $guard);

            $defaults = config("rbac.roles.{$roleEnum->value}", []);

            if ($defaults !== []) {
                $role->givePermissionTo($defaults);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
