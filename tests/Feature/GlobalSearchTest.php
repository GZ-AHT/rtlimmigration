<?php

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finds users and pages for an administrator', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $this->userWithRole(Role::Counselor)->update(['name' => 'Rafiq Counselor']);

    $groups = collect($this->actingAs($admin)->getJson(route('search', ['q' => 'rafiq']))->assertOk()->json('groups'));

    expect($groups->pluck('group')->all())->toContain('Users')
        ->and($groups->firstWhere('group', 'Users')['results'][0]['title'])->toBe('Rafiq Counselor');

    $pages = collect($this->actingAs($admin)->getJson(route('search', ['q' => 'roles']))->json('groups'))->firstWhere('group', 'Pages');
    expect($pages['results'][0]['url'])->toBe('/admin/roles');
});

it('does not let students search users', function () {
    $this->userWithRole(Role::Counselor)->update(['name' => 'Rafiq Counselor']);

    $groups = $this->actingAs($this->userWithRole(Role::Student))
        ->getJson(route('search', ['q' => 'rafiq']))
        ->json('groups');

    expect(collect($groups)->pluck('group'))->not->toContain('Users');
});

it('treats SQL wildcards literally', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);

    $groups = collect($this->actingAs($admin)->getJson(route('search', ['q' => '%%']))->json('groups'));

    expect($groups->firstWhere('group', 'Users'))->toBeNull();
});

it('needs at least two characters and requires sign-in', function () {
    $this->actingAs($this->userWithRole(Role::SuperAdmin))
        ->getJson(route('search', ['q' => 'a']))
        ->assertOk()
        ->assertJson(['groups' => []]);

    auth()->logout();
    $this->getJson(route('search', ['q' => 'abc']))->assertUnauthorized();
});
