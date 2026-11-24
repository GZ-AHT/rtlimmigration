<?php

use App\Enums\Area;
use App\Enums\Role;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function navTitles(array $groups): array
{
    return collect($groups)->flatMap(fn ($g) => collect($g['items'])->pluck('title'))->all();
}

// REQ §25 lists 26 modules; Skill Migration (owner's request, 2026-11-18) is the 27th and
// Video Consultations (owner's request, 2026-11-24) the 28th.
it('shows super admin all 26 REQ §25 admin modules plus Skill Migration and Video Consultations when planned items are visible', function () {
    config(['navigation.show_planned' => true]);

    $titles = navTitles(app(Navigation::class)->for(Area::Admin, $this->userWithRole(Role::SuperAdmin)));

    expect($titles)->toHaveCount(28)
        ->and($titles)->toContain('Skill Migration', 'Video Consultations')
        ->and($titles[0])->toBe('Dashboard')
        ->and($titles[27])->toBe('Security & Audit Logs');
});

/** A small fixed menu so these tests don't change as modules are built. */
function fixtureMenu(): void
{
    config(['navigation.areas.admin' => [
        ['label' => 'Group', 'items' => [
            ['title' => 'Home', 'icon' => 'home', 'route' => 'admin.dashboard', 'permission' => 'dashboard.view', 'phase' => 1],
            ['title' => 'Later', 'icon' => 'x', 'route' => null, 'permission' => null, 'phase' => 9],
            ['title' => 'Parent', 'icon' => 'folder', 'children' => [
                ['title' => 'Built child', 'route' => 'admin.users.index', 'permission' => 'users.view', 'phase' => 1],
                ['title' => 'Planned child', 'route' => null, 'permission' => 'leads.view', 'phase' => 2],
            ]],
        ]],
    ]]);
}

it('shows a sub-menu parent when any child is visible', function () {
    fixtureMenu();
    config(['navigation.show_planned' => true]);

    // A lead manager can see the planned child (leads.view) but not users.view.
    $parent = app(Navigation::class)->for(Area::Admin, $this->userWithRole(Role::LeadManager))[0]['items'][2];

    expect($parent['title'])->toBe('Parent')
        ->and(collect($parent['children'])->pluck('title')->all())->toBe(['Planned child'])
        ->and($parent['planned'])->toBeTrue();
});

it('filters the admin menu by permission for a lead manager', function () {
    config(['navigation.show_planned' => true]);

    $titles = navTitles(app(Navigation::class)->for(Area::Admin, $this->userWithRole(Role::LeadManager)));

    expect($titles)->toContain('Dashboard', 'Lead & User Management', 'Ticket & Contact')
        ->not->toContain('Payment & Accounts', 'Roles & Permissions', 'System Settings');
});

it('hides modules that are not built yet when show_planned is off', function () {
    fixtureMenu();
    config(['navigation.show_planned' => false]);

    $groups = app(Navigation::class)->for(Area::Admin, $this->userWithRole(Role::SuperAdmin));

    expect(navTitles($groups))->toBe(['Home', 'Parent'])
        ->and($groups[0]['items'][0]['href'])->toBe('/admin')
        ->and(collect($groups[0]['items'][1]['children'])->pluck('title')->all())->toBe(['Built child']);
});

it('links every counselor module now that Phase 12 has built Reports', function () {
    config(['navigation.show_planned' => true]);

    $items = collect(app(Navigation::class)->for(Area::Counselor, $this->userWithRole(Role::Counselor)))
        ->flatMap(fn ($g) => $g['items'])->keyBy('title');

    expect($items['My Dashboard']['planned'])->toBeFalse()
        ->and($items['My Leads']['planned'])->toBeFalse()
        ->and($items['Visa Processing']['planned'])->toBeFalse()
        ->and($items['Email']['planned'])->toBeFalse()
        ->and($items['Reports']['planned'])->toBeFalse()
        ->and($items['Reports']['href'])->toBe(route('counselor.reports.hub', absolute: false));
});

it('shares the sidebar of the current area with the page', function () {
    config(['navigation.show_planned' => false]);

    $this->actingAs($this->userWithRole(Role::Accounts))
        ->get(route('accounts.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('accounts/Dashboard')
            ->where('area.key', 'accounts')
            ->where('navigation.0.items.0.title', 'Accounts Dashboard')
            ->where('auth.isSuperAdmin', false)
        );
});

it('uses the home area sidebar on shared screens like profile settings', function () {
    $this->actingAs($this->userWithRole(Role::Counselor))
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('area.key', 'counselor'));
});
