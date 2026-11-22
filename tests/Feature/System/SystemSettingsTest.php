<?php

use App\Enums\Role;
use App\Models\LoginHistory;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SecurityAlert;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\WebsiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/* Phase 11: system settings, maintenance, security and audit logs (REQ §23). */

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed([SettingsSeeder::class, WebsiteContentSeeder::class]);
    $this->admin = $this->userWithRole(Role::SuperAdmin);
});

function sys(string $key, mixed $value, bool $encrypted = false): void
{
    app(SettingsRepository::class)->set($key, $value, encrypted: $encrypted);
}

/* ------------------------------------------------------------------ screens */

it('shows server information without secrets, and only to people who can manage settings', function () {
    $this->actingAs($this->admin)->get(route('admin.system.settings', ['tab' => 'overview']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/system/Settings')->where('tab', 'overview')
            ->has('overview.Application.Laravel')->has('overview.Database.Driver')->has('storage.free')
            ->where('overview', fn ($o) => ! str_contains(json_encode($o), (string) config('database.connections.mysql.password') ?: '@@none@@')));

    $this->actingAs($this->userWithRole(Role::Counselor))->get(route('admin.system.settings'))->assertForbidden();
    $this->actingAs($this->userWithRole(Role::Counselor))->get(route('admin.system.audit'))->assertForbidden();
});

it('clears caches from the cache tools', function () {
    $this->actingAs($this->admin)->post(route('admin.system.cache.clear', 'view'))->assertRedirect();
    $this->actingAs($this->admin)->post(route('admin.system.cache.clear', 'all'))->assertRedirect();
    $this->actingAs($this->admin)->post(route('admin.system.cache.clear', 'bogus'))->assertNotFound();
    $this->actingAs($this->userWithRole(Role::Counselor))->post(route('admin.system.cache.clear', 'all'))->assertForbidden();
});

it('reads the newest error log entries, filtered by level', function () {
    $path = storage_path('logs/phase11-test.log');
    file_put_contents($path, "[2026-11-21 10:00:00] local.INFO: Something happened\n[2026-11-21 10:01:00] local.ERROR: Boom went the thing\n#0 /app/stack.php(1): trace\n#1 {main}\n");
    touch($path, time() + 5);

    try {
        $this->actingAs($this->admin)->get(route('admin.system.settings', ['tab' => 'logs']))->assertInertia(fn (Assert $p) => $p
            ->where('logs.file', 'phase11-test.log')->where('logs.entries.0.level', 'error')->where('logs.entries.0.message', 'Boom went the thing')
            ->where('logs.entries.0.detail', fn ($d) => str_contains($d, 'trace'))->has('logs.entries', 2));

        $this->actingAs($this->admin)->get(route('admin.system.settings', ['tab' => 'logs', 'level' => 'info']))->assertInertia(fn (Assert $p) => $p->has('logs.entries', 1));
    } finally {
        unlink($path);
    }
});

it('lists admin activity and sign-ins, with search', function () {
    LoginHistory::create(['user_id' => $this->admin->id, 'email' => $this->admin->email, 'event' => 'failed', 'ip_address' => '198.51.100.7']);
    activity('cms')->causedBy($this->admin)->log('Redirect added');

    $this->actingAs($this->admin)->get(route('admin.system.audit'))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/system/Audit')->where('tab', 'activity')->where('rows.data', fn ($d) => collect($d)->pluck('description')->contains('Redirect added')));

    $this->actingAs($this->admin)->get(route('admin.system.audit', ['tab' => 'logins', 'event' => 'failed', 'search' => '198.51']))
        ->assertInertia(fn (Assert $p) => $p->where('rows.data.0.ip', '198.51.100.7')->has('rows.data', 1));
});

/* ----------------------------------------------------------------- settings */

it('saves a Google Maps key and refuses one that does not look like a key', function () {
    $put = fn (string $key) => $this->actingAs($this->admin)->put(route('admin.system.settings.update', 'integrations'), ['google_maps_key' => $key, 'whatsapp_enabled' => true]);

    $put('AIzaSyExampleKey_1234567890abcd')->assertSessionHasNoErrors();
    expect(app(SettingsRepository::class)->get('integrations.google_maps_key'))->toBe('AIzaSyExampleKey_1234567890abcd');

    $put('<script>alert(1)</script>')->assertSessionHasErrors('google_maps_key');
    $put('short')->assertSessionHasErrors('google_maps_key');
    $put('')->assertSessionHasNoErrors();
    expect((string) app(SettingsRepository::class)->get('integrations.google_maps_key'))->toBe('');
});

it('saves settings, validates them, and keeps secrets encrypted and out of the page', function () {
    $this->actingAs($this->admin)->put(route('admin.system.settings.update', 'integrations'), [
        'fb_pixel_id' => '123456789012345', 'recaptcha_enabled' => true, 'recaptcha_site_key' => 'site-key_1', 'recaptcha_secret' => 'super-secret-value',
        'whatsapp_enabled' => true, 'whatsapp_message' => 'Hi',
    ])->assertSessionHasNoErrors();

    $row = Setting::where('group', 'integrations')->where('key', 'recaptcha_secret')->first();
    expect($row->is_encrypted)->toBeTrue()->and((string) $row->value)->not->toContain('super-secret-value')
        ->and(app(SettingsRepository::class)->get('integrations.recaptcha_secret'))->toBe('super-secret-value');

    $this->actingAs($this->admin)->get(route('admin.system.settings', ['tab' => 'integrations']))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('values.integrations.recaptcha_secret', '')->where('secrets.integrations.recaptcha_secret', true))
        ->assertDontSee('super-secret-value', false);

    // A blank secret keeps the saved one; public settings never carry it.
    $this->actingAs($this->admin)->put(route('admin.system.settings.update', 'integrations'), ['recaptcha_secret' => '', 'fb_pixel_id' => '123456789012345', 'whatsapp_enabled' => true])->assertSessionHasNoErrors();
    expect(app(SettingsRepository::class)->get('integrations.recaptcha_secret'))->toBe('super-secret-value')
        ->and(json_encode(app(SettingsRepository::class)->public()))->not->toContain('super-secret-value');

    $this->actingAs($this->admin)->put(route('admin.system.settings.update', 'integrations'), ['fb_pixel_id' => 'abc'])->assertSessionHasErrors('fb_pixel_id');
    $this->actingAs($this->admin)->put(route('admin.system.settings.update', 'security'), ['password_min_length' => 3, 'login_max_attempts' => 5, 'login_lockout_minutes' => 1, 'session_lifetime' => 120, 'blocked_ips' => "not-an-ip\n203.0.113.0/24"])
        ->assertSessionHasErrors(['password_min_length', 'blocked_ips']);
    $this->actingAs($this->admin)->put(route('admin.system.settings.update', 'nope'), [])->assertNotFound();
    $this->actingAs($this->userWithRole(Role::Counselor))->put(route('admin.system.settings.update', 'security'), [])->assertForbidden();
});

it('lets only the right permission change each group, and logs the change without values', function () {
    $manager = User::factory()->create()->assignRole(Role::Counselor->value);
    $manager->givePermissionTo(['area.admin', 'integrations.manage']);

    $this->actingAs($manager)->get(route('admin.system.settings', ['tab' => 'security']))->assertInertia(fn (Assert $p) => $p->where('tab', 'integrations')->has('groups.integrations')->missing('groups.security'));
    $this->actingAs($manager)->put(route('admin.system.settings.update', 'maintenance'), ['enabled' => true])->assertForbidden();

    $this->actingAs($this->admin)->put(route('admin.system.settings.update', 'integrations'), ['recaptcha_secret' => 'abc123secret'])->assertSessionHasNoErrors();
    $log = Activity::latest('id')->first();
    expect($log->description)->toBe('Integrations settings changed')->and(json_encode($log->properties))->not->toContain('abc123secret');
});

/* -------------------------------------------------------------- maintenance */

it('shows the maintenance page to visitors, but not to people who manage settings or to listed addresses', function () {
    sys('maintenance.enabled', true);
    sys('maintenance.message', 'Back at noon.');

    $this->get('/faq')->assertStatus(503)->assertInertia(fn (Assert $p) => $p->component('website/Error')->where('status', 503)->where('message', 'Back at noon.'));
    $this->get('/login')->assertOk();
    expect($this->postJson('/payments/gateway/sslcommerz/ipn')->status())->not->toBe(503);
    $this->actingAs($this->admin)->get('/faq')->assertOk();

    auth()->logout();
    sys('maintenance.allow_ips', "10.0.0.5\n127.0.0.1");
    $this->get('/faq')->assertOk();
});

it('follows the maintenance schedule', function () {
    sys('maintenance.starts_at', now()->addHour()->format('Y-m-d\TH:i'));
    sys('maintenance.ends_at', now()->addHours(2)->format('Y-m-d\TH:i'));
    $this->get('/faq')->assertOk();

    $this->travel(90)->minutes();
    $this->get('/faq')->assertStatus(503);

    $this->travel(60)->minutes();
    $this->get('/faq')->assertOk();
});

/* ----------------------------------------------------------------- security */

it('blocks listed addresses everywhere, and restricts the staff area to the allow list', function () {
    sys('security.blocked_ips', '127.0.0.0/8');
    $this->get('/faq')->assertForbidden();

    sys('security.blocked_ips', '');
    sys('security.admin_ip_allowlist', '203.0.113.0/24');
    $this->actingAs($this->admin)->get('/admin')->assertForbidden();
    $this->actingAs($this->admin)->get('/faq')->assertOk();

    sys('security.admin_ip_allowlist', "203.0.113.0/24\n127.0.0.1");
    $this->actingAs($this->admin)->get('/admin')->assertOk();
});

it('applies the saved password policy', function () {
    $rule = fn () => Validator::make(['p' => 'abcdefgh'], ['p' => [Password::default()]])->passes();

    sys('security.password_min_length', 8);
    expect($rule())->toBeTrue();

    sys('security.password_numbers', true);
    expect($rule())->toBeFalse();

    sys('security.password_numbers', false);
    sys('security.password_min_length', 12);
    expect($rule())->toBeFalse();
});

it('locks sign-in after the configured number of attempts and tells security managers', function () {
    Notification::fake();
    sys('security.login_max_attempts', 3);
    sys('security.login_lockout_minutes', 5);
    $user = User::factory()->create();

    foreach (range(1, 3) as $i) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors();
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
    Notification::assertSentTo($this->admin, SecurityAlert::class, fn ($n) => $n->headline === 'Sign-in lockout');
    RateLimiter::clear(Str::transliterate(Str::lower($user->email).'|127.0.0.1'));
});

it('tells security managers when staff sign in from a new address, once', function () {
    Notification::fake();
    $staff = $this->userWithRole(Role::Counselor);
    LoginHistory::create(['user_id' => $staff->id, 'email' => $staff->email, 'event' => 'login', 'ip_address' => '198.51.100.9']);

    $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password'])->assertRedirect();
    Notification::assertSentTo($this->admin, SecurityAlert::class, fn ($n) => $n->headline === 'Staff sign-in from a new address');

    sys('security.notify_new_ip', false);
    Notification::fake();
    auth()->logout();
    LoginHistory::where('ip_address', '127.0.0.1')->delete();
    $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password']);
    Notification::assertNothingSent();
});

/* ------------------------------------------------------------- integrations */

it('adds the Facebook Pixel and honours the WhatsApp switch on the website', function () {
    app(SettingsRepository::class)->set('integrations.fb_pixel_id', '123456789012345', public: true);
    $this->get('/')->assertSee("fbq('init','123456789012345')", false);

    app(SettingsRepository::class)->set('integrations.fb_pixel_id', "1');alert(1);//", public: true);
    $this->get('/')->assertDontSee('fbq(', false);

    app(SettingsRepository::class)->set('integrations.whatsapp_enabled', false, public: true);
    $this->get('/')->assertInertia(fn (Assert $p) => $p->where('site.integrations.whatsapp_enabled', false));
});

it('checks reCAPTCHA on public forms when it is on, and not otherwise', function () {
    $enquiry = ['name' => 'Rafi Ahmed', 'email' => 'rafi@example.com', 'message' => 'Hello, I would like to ask about studying in Canada.', 'consent' => true];

    // Off: no token needed.
    $google = new ArrayObject(['body' => ['success' => true, 'score' => 0.9], 'status' => 200]);
    Http::fake(fn () => Http::response($google['body'], $google['status']));
    $this->post('/enquiry', $enquiry)->assertSessionDoesntHaveErrors('recaptcha');

    app(SettingsRepository::class)->set('integrations.recaptcha_enabled', true, public: true);
    app(SettingsRepository::class)->set('integrations.recaptcha_site_key', 'site', public: true);
    sys('integrations.recaptcha_secret', 'shh', encrypted: true);

    // No token, a bot score, then a good score.
    $this->post('/enquiry', $enquiry)->assertSessionHasErrors('recaptcha');

    $google['body'] = ['success' => true, 'score' => 0.1];
    $this->post('/enquiry', [...$enquiry, 'recaptcha_token' => 'abc'])->assertSessionHasErrors('recaptcha');

    $google['body'] = ['success' => true, 'score' => 0.9];
    $this->post('/enquiry', [...$enquiry, 'recaptcha_token' => 'abc'])->assertSessionDoesntHaveErrors('recaptcha');

    // Google down: the form still works.
    $google['body'] = [];
    $google['status'] = 500;
    $this->post('/enquiry', [...$enquiry, 'recaptcha_token' => 'abc'])->assertSessionDoesntHaveErrors('recaptcha');
});
