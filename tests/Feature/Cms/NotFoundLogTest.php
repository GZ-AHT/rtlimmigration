<?php

use App\Enums\Role;
use App\Models\NotFoundLog;
use App\Support\Seo\NotFoundRecorder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\WebsiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed([SettingsSeeder::class, ReferenceDataSeeder::class, WebsiteContentSeeder::class]);
    $this->admin = $this->userWithRole(Role::SuperAdmin);
});

describe('recording', function () {
    it('counts each visit to a missing page and ignores the query string', function () {
        $this->get('/old-visa-page')->assertNotFound();
        $this->get('/old-visa-page?utm=x')->assertNotFound();
        $this->get('/Old-Visa-Page/')->assertNotFound();

        $log = NotFoundLog::sole();
        expect($log->path)->toBe('/old-visa-page')->and($log->hits)->toBe(3);
    });

    it('keeps the latest outside referrer but ignores links from our own pages', function () {
        $this->get('/gone', ['Referer' => 'https://blog.example.org/post'])->assertNotFound();
        expect(NotFoundLog::sole()->referrer)->toBe('https://blog.example.org/post');

        $this->get('/gone', ['Referer' => url('/about-us')])->assertNotFound();
        expect(NotFoundLog::sole())->hits->toBe(2)->referrer->toBe('https://blog.example.org/post');
    });

    it('does not record pages that exist', function () {
        $this->get('/')->assertOk();
        $this->get('/about-us')->assertOk();

        expect(NotFoundLog::count())->toBe(0);
    });

    it('skips scanner probes, staff areas, non-GET requests and signed-in staff', function () {
        $this->get('/wp-login.php')->assertNotFound();
        $this->get('/admin/nothing-here');
        $this->get('/.env')->assertNotFound();
        $this->get('/backup.zip')->assertNotFound();
        $this->post('/definitely-missing', [])->assertStatus(405);
        $this->actingAs($this->admin)->get('/typo-by-staff')->assertNotFound();

        expect(NotFoundLog::count())->toBe(0);
    });

    it('stops taking new addresses at the limit but still counts known ones', function () {
        DB::table('not_found_logs')->insert(array_map(fn (int $i) => ['path' => "/p{$i}", 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()], range(1, NotFoundRecorder::MAX_PATHS - 1)));
        $this->get('/the-last-one')->assertNotFound();
        $this->get('/one-too-many')->assertNotFound();
        $this->get('/the-last-one')->assertNotFound();

        expect(NotFoundLog::count())->toBe(NotFoundRecorder::MAX_PATHS)
            ->and(NotFoundLog::where('path', '/the-last-one')->sole()->hits)->toBe(2)
            ->and(NotFoundLog::where('path', '/one-too-many')->exists())->toBeFalse();
    });

    it('still shows the normal 404 page', function () {
        $this->get('/gone')->assertNotFound()->assertInertia(fn (Assert $p) => $p->component('website/Error')->where('status', 404));
    });
});

describe('admin screen', function () {
    it('lists the busiest missing addresses first, and offers a redirect', function () {
        NotFoundLog::create(['path' => '/rare', 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()]);
        NotFoundLog::create(['path' => '/busy', 'hits' => 40, 'referrer' => 'https://x.test/', 'first_seen_at' => now(), 'last_seen_at' => now()]);

        $this->actingAs($this->admin)->get(route('admin.seo.not-found.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('admin/seo/NotFound')->where('total', 2)
                ->where('logs.data.0.path', '/busy')->where('logs.data.0.hits', 40)
                ->where('logs.data.0.redirect_url', route('admin.seo.redirects.index', ['from' => '/busy'], false)));

        $this->get(route('admin.seo.not-found.index', ['search' => 'zzz']))->assertInertia(fn (Assert $p) => $p->has('logs.data', 0));
        $this->get(route('admin.seo.not-found.index', ['search' => 'rare']))->assertInertia(fn (Assert $p) => $p->has('logs.data', 1));
    });

    it('fills in the redirect form from a logged address', function () {
        $this->actingAs($this->admin)->get(route('admin.seo.redirects.index', ['from' => '/busy']))
            ->assertInertia(fn (Assert $p) => $p->where('prefill', '/busy'));
        $this->get(route('admin.seo.redirects.index'))->assertInertia(fn (Assert $p) => $p->where('prefill', ''));
    });

    it('removes one address or clears the log', function () {
        $a = NotFoundLog::create(['path' => '/a', 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()]);
        NotFoundLog::create(['path' => '/b', 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()]);

        $this->actingAs($this->admin)->delete(route('admin.seo.not-found.destroy', $a))->assertSessionHasNoErrors();
        expect(NotFoundLog::pluck('path')->all())->toBe(['/b']);

        $this->delete(route('admin.seo.not-found.clear'))->assertSessionHasNoErrors();
        expect(NotFoundLog::count())->toBe(0);
    });

    it('is limited to people who manage SEO', function () {
        $log = NotFoundLog::create(['path' => '/a', 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()]);

        foreach ([Role::LeadManager, Role::Counselor, Role::Accounts, Role::Student] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get('/admin/seo/not-found')->assertForbidden();
            $this->actingAs($user)->delete('/admin/seo/not-found/'.$log->id)->assertForbidden();
            $this->actingAs($user)->delete('/admin/seo/not-found')->assertForbidden();
        }

        expect(NotFoundLog::count())->toBe(1);
    });
});
