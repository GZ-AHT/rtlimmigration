<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as RoleModel;

uses(RefreshDatabase::class);

function roleAdmin(array $extra = []): User
{
    $role = RoleModel::findOrCreate('role-admin', 'web');
    $role->syncPermissions(['area.admin', 'roles.manage', 'blog.view', 'blog.manage', ...$extra]);

    return User::factory()->create()->assignRole('role-admin');
}

it('lists roles with user and permission counts', function () {
    $this->actingAs($this->userWithRole(Role::SuperAdmin))
        ->get(route('admin.roles.index'))
        ->assertInertia(fn ($page) => $page->component('admin/roles/Index')
            ->has('roles', 5)
            ->where('roles.0.name', 'super-admin')
            ->where('roles.0.full_access', true)
            ->where('roles.0.can.update', false)
            ->where('roles.0.users_count', 1));
});

it('requires roles.manage', function () {
    $this->actingAs($this->userWithRole(Role::LeadManager))
        ->get(route('admin.roles.index'))
        ->assertForbidden();
});

it('creates a custom role with permissions and logs it', function () {
    $this->actingAs($this->userWithRole(Role::SuperAdmin))
        ->post(route('admin.roles.store'), ['name' => 'Content Editor', 'permissions' => ['area.admin', 'blog.view', 'blog.manage']])
        ->assertRedirect(route('admin.roles.index'));

    $role = RoleModel::findByName('content-editor');

    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['area.admin', 'blog.manage', 'blog.view'])
        ->and(Activity::where('event', 'role_created')->exists())->toBeTrue();
});

it('rejects duplicate, reserved and unknown names or permissions', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    RoleModel::findOrCreate('content-editor', 'web');

    $this->actingAs($admin)->post(route('admin.roles.store'), ['name' => 'Content Editor', 'permissions' => []])
        ->assertSessionHasErrors('slug');
    $this->actingAs($admin)->post(route('admin.roles.store'), ['name' => 'Super Admin', 'permissions' => []])
        ->assertSessionHasErrors('slug');
    $this->actingAs($admin)->post(route('admin.roles.store'), ['name' => 'X', 'permissions' => ['made.up']])
        ->assertSessionHasErrors('permissions.0');
});

it('blocks privilege escalation: you can only grant permissions you hold', function () {
    $this->actingAs(roleAdmin())
        ->post(route('admin.roles.store'), ['name' => 'Sneaky', 'permissions' => ['blog.view', 'payments.confirm']])
        ->assertSessionHasErrors('permissions.1');

    expect(RoleModel::where('name', 'sneaky')->exists())->toBeFalse();
});

it('keeps permissions the editor cannot see when saving', function () {
    $target = RoleModel::findOrCreate('finance-helper', 'web');
    $target->syncPermissions(['blog.view', 'payments.confirm']);

    $this->actingAs(roleAdmin())
        ->put(route('admin.roles.update', $target), ['name' => 'Finance Helper', 'permissions' => ['blog.manage']])
        ->assertRedirect();

    expect($target->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['blog.manage', 'payments.confirm']);
});

it('updates built-in role permissions but not their names', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $counselor = RoleModel::findByName(Role::Counselor->value);

    $this->actingAs($admin)->put(route('admin.roles.update', $counselor), ['name' => 'Renamed', 'permissions' => []])
        ->assertSessionHasErrors('name');

    $this->actingAs($admin)->put(route('admin.roles.update', $counselor), ['permissions' => ['area.counselor', 'leads.view']])
        ->assertRedirect();

    expect($counselor->fresh()->name)->toBe('counselor')
        ->and($counselor->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['area.counselor', 'leads.view']);
});

it('does not allow editing Super Admin or deleting built-in roles', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);

    $this->actingAs($admin)->get(route('admin.roles.edit', RoleModel::findByName('super-admin')))->assertForbidden();
    $this->actingAs($admin)->delete(route('admin.roles.destroy', RoleModel::findByName('student')))->assertForbidden();
});

it('only deletes custom roles that have no users', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $role = RoleModel::findOrCreate('temp', 'web');
    $member = User::factory()->create()->assignRole('temp');

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))->assertSessionHasErrors('role');

    $member->removeRole('temp');
    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));

    expect(RoleModel::where('name', 'temp')->exists())->toBeFalse();
});
