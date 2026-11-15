<?php

use App\Enums\Role as RoleEnum;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates every built-in role and registered permission', function () {
    $expected = collect(config('rbac.permissions'))->flatMap(fn ($p) => array_keys($p));

    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(collect(RoleEnum::cases())->pluck('value')->sort()->values()->all())
        ->and(Permission::count())->toBe($expected->count());
});

it('is idempotent and keeps permissions an admin added', function () {
    Role::findByName(RoleEnum::Student->value)->givePermissionTo('blog.view');

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe(count(RoleEnum::cases()))
        ->and(Role::findByName(RoleEnum::Student->value)->hasPermissionTo('blog.view'))->toBeTrue();
});

it('does not let accounts staff change visa decisions (REQ §27 rule 5)', function () {
    $accounts = $this->userWithRole(RoleEnum::Accounts);

    expect($accounts->can('payments.confirm'))->toBeTrue()
        ->and($accounts->can('applications.decide'))->toBeFalse()
        ->and($accounts->can('applications.update'))->toBeFalse();
});

it('does not let counselors confirm payments (REQ §27 rule 6)', function () {
    $counselor = $this->userWithRole(RoleEnum::Counselor);

    expect($counselor->can('payments.request'))->toBeTrue()
        ->and($counselor->can('payments.confirm'))->toBeFalse()
        ->and($counselor->can('payments.verify'))->toBeFalse();
});

it('gives super admin every permission (REQ §27 rule 1)', function () {
    $admin = $this->userWithRole(RoleEnum::SuperAdmin);

    expect($admin->can('refunds.approve'))->toBeTrue()
        ->and($admin->can('cms.custom-code'))->toBeTrue()
        ->and($admin->can('some.unregistered-permission'))->toBeTrue();
});
