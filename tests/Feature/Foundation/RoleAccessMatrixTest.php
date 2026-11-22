<?php

use App\Enums\Role;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * REQ §31.5 "Verify role-based access for Super Admin, Lead Manager,
 * Counselor, Accounts and Student/User roles". Every dashboard page that
 * needs no record id is requested as each role:
 *
 *   - a role without the area's permission is always refused (403);
 *   - no role, in any area, ever gets a server error (5xx).
 *
 * Pages that take a record are covered by each module's own policy tests.
 */
const AREA_ROLES = [
    'admin' => [Role::SuperAdmin, Role::LeadManager],
    'counselor' => [Role::SuperAdmin, Role::Counselor],
    'accounts' => [Role::SuperAdmin, Role::Accounts],
    'portal' => [Role::SuperAdmin, Role::Student],
];

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
});

/** @return list<string> GET uris of an area that need no parameters. */
function areaPages(string $area): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $r) => in_array('GET', $r->methods(), true)
            && ($r->uri() === $area || str_starts_with($r->uri(), "{$area}/"))
            && ! str_contains($r->uri(), '{')
            // Downloads and streams are covered by their own tests.
            && ! str_contains($r->uri(), 'export') && ! str_contains($r->uri(), 'download') && ! str_contains($r->uri(), 'backup'))
        ->map(fn (LaravelRoute $r) => '/'.$r->uri())
        ->unique()->sort()->values()->all();
}

it('keeps every role out of dashboards it does not belong to', function (string $area) {
    $pages = areaPages($area);
    expect($pages)->not->toBeEmpty();

    $outsiders = collect(Role::cases())->reject(fn (Role $role) => in_array($role, AREA_ROLES[$area], true));
    $failures = [];

    foreach ($outsiders as $role) {
        $user = $this->userWithRole($role);

        foreach ($pages as $page) {
            $status = $this->actingAs($user)->get($page)->getStatusCode();

            if ($status !== 403) {
                $failures[] = "{$role->value} → {$page}: {$status}";
            }
        }
    }

    expect($failures)->toBe([]);
})->with(['admin', 'counselor', 'accounts', 'portal']);

it('never answers a dashboard page with a server error', function (string $area) {
    $failures = [];

    foreach (AREA_ROLES[$area] as $role) {
        $user = $this->userWithRole($role);

        foreach (areaPages($area) as $page) {
            $status = $this->actingAs($user)->get($page)->getStatusCode();

            if ($status >= 500) {
                $failures[] = "{$role->value} → {$page}: {$status}";
            }
        }
    }

    expect($failures)->toBe([]);
})->with(['admin', 'counselor', 'accounts', 'portal']);

it('sends signed-out visitors to the sign-in page for every dashboard page', function (string $area) {
    $failures = [];

    foreach (areaPages($area) as $page) {
        $response = $this->get($page);

        if (! $response->isRedirect() || ! str_contains((string) $response->headers->get('Location'), 'login')) {
            $failures[] = "{$page}: {$response->getStatusCode()}";
        }
    }

    expect($failures)->toBe([]);
})->with(['admin', 'counselor', 'accounts', 'portal']);

it('lets a Super Admin open the create-user page (it was once swallowed by users/{user})', function () {
    $this->actingAs($this->userWithRole(Role::SuperAdmin))->get('/admin/users/create')->assertOk();
});
