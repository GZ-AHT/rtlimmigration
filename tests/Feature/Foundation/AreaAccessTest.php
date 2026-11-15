<?php

use App\Enums\Area;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

dataset('roles', [
    'super admin' => [Role::SuperAdmin, Area::Admin],
    'lead manager' => [Role::LeadManager, Area::Admin],
    'counselor' => [Role::Counselor, Area::Counselor],
    'accounts' => [Role::Accounts, Area::Accounts],
    'student' => [Role::Student, Area::Portal],
]);

it('sends each role from /dashboard to its own area', function (Role $role, Area $area) {
    $this->actingAs($this->userWithRole($role))
        ->get(route('dashboard'))
        ->assertRedirect(route($area->dashboardRoute()));
})->with('roles');

it('lets each role open its own dashboard', function (Role $role, Area $area) {
    $this->actingAs($this->userWithRole($role))
        ->get(route($area->dashboardRoute()))
        ->assertOk();
})->with('roles');

it('blocks roles from areas that are not theirs', function (Role $role, Area $own) {
    $user = $this->userWithRole($role);

    foreach (Area::cases() as $area) {
        if ($area === $own) {
            continue;
        }

        $this->actingAs($user)->get(route($area->dashboardRoute()))->assertForbidden();
    }
})->with([
    'lead manager' => [Role::LeadManager, Area::Admin],
    'counselor' => [Role::Counselor, Area::Counselor],
    'accounts' => [Role::Accounts, Area::Accounts],
    'student' => [Role::Student, Area::Portal],
]);

it('lets super admin enter every area', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);

    foreach (Area::cases() as $area) {
        $this->actingAs($admin)->get(route($area->dashboardRoute()))->assertOk();
    }
});

it('redirects guests to login', function () {
    foreach (Area::cases() as $area) {
        $this->get(route($area->dashboardRoute()))->assertRedirect(route('login'));
    }
});

it('refuses /dashboard for a user with no role', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertForbidden();
});

it('requires a verified email to enter an area', function () {
    $user = User::factory()->unverified()->create()->assignRole(Role::Student->value);

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertRedirect(route('verification.notice'));
});

it('gives self-registered users the student role', function () {
    $this->post(route('register.store'), [
        'name' => 'New Client',
        'email' => 'client@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::where('email', 'client@example.com')->first()->hasRole(Role::Student->value))->toBeTrue();
});
