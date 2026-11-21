<?php

use App\Enums\Role;
use App\Models\Backup;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use App\Models\Webhook;
use App\Support\Backups\BackupService;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/* Phase 11: webhooks and backups (REQ §23.3, §23.5). */

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed([SettingsSeeder::class, ReferenceDataSeeder::class]);
    $this->admin = $this->userWithRole(Role::SuperAdmin);
    // Backups made by these tests go to a scratch folder, never the real one.
    config(['filesystems.backups_path' => storage_path('framework/testing/backups-'.uniqid())]);
});

afterEach(function () {
    $dir = BackupService::directory();

    foreach (glob($dir.'/*') ?: [] as $file) {
        @unlink($file);
    }

    @rmdir($dir);
});

const HOOK_URL = 'https://93.184.216.34/hooks/rtl';

function hook(array $events = ['lead.created'], array $attributes = []): Webhook
{
    return Webhook::create(['name' => 'CRM', 'url' => HOOK_URL, 'secret' => 'whsec_test', 'events' => $events, 'is_active' => true, ...$attributes]);
}

function newLead(): Lead
{
    return Lead::create(['lead_number' => 'LD-'.fake()->unique()->numerify('######'), 'name' => 'Private Person', 'email' => 'private@example.com', 'phone' => '+8801711000000', 'source' => 'website']);
}

/* ----------------------------------------------------------------- webhooks */

it('manages webhooks, shows the secret once, and refuses unsafe addresses', function () {
    $this->actingAs($this->admin)->get(route('admin.system.webhooks.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/system/Webhooks')->has('webhooks', 0)->has('events', 5));

    $this->actingAs($this->admin)->post(route('admin.system.webhooks.store'), ['name' => 'CRM', 'url' => HOOK_URL, 'events' => ['lead.created']])->assertSessionHasNoErrors();
    $webhook = Webhook::first();
    expect($webhook->secret)->toStartWith('whsec_')->and(Setting::count())->toBeGreaterThan(0);
    expect(DB::table('webhooks')->value('secret'))->not->toContain('whsec_');

    // The secret is shown once, on the page that follows creating it, and never again.
    $this->actingAs($this->admin)->get(route('admin.system.webhooks.index'))->assertSee($webhook->secret, false);
    $this->actingAs($this->admin)->get(route('admin.system.webhooks.index'))->assertDontSee($webhook->secret, false);

    foreach (['http://93.184.216.34/x' => 'url', 'https://127.0.0.1/x' => 'url', 'https://10.0.0.5/x' => 'url', 'https://user:pw@93.184.216.34/x' => 'url', 'ftp://93.184.216.34' => 'url'] as $url => $field) {
        $this->actingAs($this->admin)->post(route('admin.system.webhooks.store'), ['name' => 'Bad', 'url' => $url, 'events' => ['lead.created']])->assertSessionHasErrors($field);
    }
    $this->actingAs($this->admin)->post(route('admin.system.webhooks.store'), ['name' => 'Bad', 'url' => HOOK_URL, 'events' => ['ping']])->assertSessionHasErrors('events.0');
    $this->actingAs($this->admin)->post(route('admin.system.webhooks.store'), ['name' => 'Bad', 'url' => HOOK_URL, 'events' => []])->assertSessionHasErrors('events');

    $old = $webhook->secret;
    $this->actingAs($this->admin)->post(route('admin.system.webhooks.secret', $webhook))->assertRedirect();
    expect($webhook->fresh()->secret)->not->toBe($old);

    $this->actingAs($this->admin)->put(route('admin.system.webhooks.update', $webhook), ['name' => 'CRM 2', 'url' => HOOK_URL, 'events' => ['lead.created', 'student.created'], 'is_active' => false])->assertSessionHasNoErrors();
    expect($webhook->fresh())->name->toBe('CRM 2')->is_active->toBeFalse();

    $this->actingAs($this->admin)->delete(route('admin.system.webhooks.destroy', $webhook))->assertRedirect();
    expect(Webhook::count())->toBe(0);

    $this->actingAs($this->userWithRole(Role::Counselor))->get(route('admin.system.webhooks.index'))->assertForbidden();
});

it('sends a signed event with ids only when something happens', function () {
    Http::fake(['93.184.216.34/*' => Http::response('ok', 200)]);
    $webhook = hook();

    $lead = newLead();

    Http::assertSent(function ($request) use ($lead) {
        $body = $request->body();
        $timestamp = $request->header('X-RTL-Timestamp')[0];
        $json = json_decode($body, true);

        return $request->url() === HOOK_URL && $request->header('X-RTL-Event')[0] === 'lead.created'
            && $request->header('X-RTL-Signature')[0] === 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test')
            && $json['data']['lead_id'] === $lead->id && ! str_contains($body, 'private@example.com') && ! str_contains($body, 'Private Person')
            && $json['id'] === $request->header('X-RTL-Delivery')[0];
    });
    expect($webhook->deliveries()->count())->toBe(1)->and($webhook->deliveries()->first()->succeeded)->toBeTrue()->and($webhook->fresh()->last_status)->toBe(200);
});

it('only sends events a webhook asked for, and nothing while it is off', function () {
    Http::fake();
    hook(['student.created']);
    hook(['lead.created'], ['is_active' => false, 'name' => 'Off']);

    newLead();

    Http::assertNothingSent();
});

it('records failures and switches a webhook off after too many in a row', function () {
    Http::fake(['93.184.216.34/*' => Http::response('nope', 500)]);
    $webhook = hook(attributes: ['failure_count' => Webhook::MAX_FAILURES - 1]);

    try {
        newLead();
    } catch (Throwable) {
        // A failed job in the sync queue rethrows; the delivery is still recorded.
    }

    expect($webhook->fresh())->is_active->toBeFalse()->failure_count->toBe(Webhook::MAX_FAILURES)->last_status->toBe(500)
        ->and($webhook->deliveries()->first())->succeeded->toBeFalse()->error->toBe('HTTP 500');
});

it('refuses to deliver to an address that has since become private', function () {
    Http::fake();
    $webhook = hook(attributes: ['url' => 'https://192.168.1.10/hook']);

    try {
        newLead();
    } catch (Throwable) {
    }

    Http::assertNothingSent();
    expect($webhook->deliveries()->first()->error)->toContain('private network');
});

it('sends a test event from the admin', function () {
    Http::fake(['93.184.216.34/*' => Http::response('ok', 200)]);
    $webhook = hook(['lead.created']);

    $this->actingAs($this->admin)->post(route('admin.system.webhooks.test', $webhook))->assertRedirect();

    Http::assertSent(fn ($r) => $r->header('X-RTL-Event')[0] === 'ping');
});

/* ------------------------------------------------------------------ backups */

it('writes a database backup that holds the data, and a files backup that holds the uploads', function () {
    Storage::disk('local')->put('probe-test.txt', 'hello');
    file_put_contents(storage_path('app/public/backup-probe-test.txt'), 'public file');

    $db = app(BackupService::class)->create('database', 'manual', $this->admin->id);
    $files = app(BackupService::class)->create('files', 'manual', $this->admin->id);

    expect($db)->status->toBe('done')->filename->toStartWith('db-')->and($db->size)->toBeGreaterThan(100);
    $sql = gzdecode(file_get_contents(BackupService::path($db)));
    expect($sql)->toStartWith('-- RTL-BACKUP v1 mysql')->toContain('CREATE TABLE `users`')->toContain($this->admin->email)
        ->not->toContain('CREATE TABLE `backups`')->not->toContain('INSERT INTO `sessions`')->toContain('-- END');
    foreach (explode("\n", $sql) as $line) {
        expect($line === '' || str_starts_with($line, '--') || preg_match('/^(SET FOREIGN_KEY_CHECKS=[01]|DROP TABLE IF EXISTS `|CREATE TABLE `|INSERT INTO `)/', $line))->toBeTrue();
    }

    expect($files->status)->toBe('done');
    $zip = new ZipArchive;
    $zip->open(BackupService::path($files));
    expect($zip->locateName('public/backup-probe-test.txt'))->not->toBeFalse();
    $zip->close();
    @unlink(storage_path('app/public/backup-probe-test.txt'));
});

it('creates, lists, downloads and deletes backups for people with the backup permission only', function () {
    $this->actingAs($this->admin)->post(route('admin.system.backups.store'), ['type' => 'database'])->assertRedirect();
    $backup = Backup::first();
    expect($backup)->status->toBe('done');

    $this->actingAs($this->admin)->get(route('admin.system.backups.index'))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/system/Backups')->where('backups.0.id', $backup->id)->where('canRestore', true)->has('groups.backups.fields.schedule'));

    $this->actingAs($this->admin)->get(route('admin.system.backups.download', $backup))->assertOk()->assertDownload($backup->filename);
    expect(Activity::where('description', 'Backup downloaded')->count())->toBe(1);

    $counselor = $this->userWithRole(Role::Counselor);
    $this->actingAs($counselor)->get(route('admin.system.backups.index'))->assertForbidden();
    $this->actingAs($counselor)->get(route('admin.system.backups.download', $backup))->assertForbidden();

    $this->actingAs($this->admin)->delete(route('admin.system.backups.destroy', $backup))->assertRedirect();
    expect(Backup::whereKey($backup->id)->exists())->toBeFalse()->and(is_file(BackupService::directory().'/'.$backup->filename))->toBeFalse();
});

it('restores only for a Super Admin who gives their password and the phrase, and keeps a safety backup', function () {
    // An INSERT-only backup: a real restore replaces tables (DDL), which would end the test's database transaction.
    $name = 'db-test-'.uniqid().'.sql.gz';
    $gz = gzopen(BackupService::directory().'/'.$name, 'wb');
    gzwrite($gz, "-- RTL-BACKUP v1 mysql\nSET FOREIGN_KEY_CHECKS=0;\nINSERT INTO `settings` (`group`, `key`, `value`, `is_encrypted`, `is_public`) VALUES ('restore', 'probe', '\"restored\"', 0, 0);\nSET FOREIGN_KEY_CHECKS=1;\n-- END\n");
    gzclose($gz);
    $backup = Backup::create(['type' => 'database', 'trigger' => 'manual', 'filename' => $name, 'size' => 10, 'status' => 'done']);
    $url = route('admin.system.backups.restore', $backup);

    $this->actingAs($this->admin)->post($url, ['password' => 'wrong', 'phrase' => 'RESTORE'])->assertSessionHasErrors('password');
    $this->actingAs($this->admin)->post($url, ['password' => 'password', 'phrase' => 'restore'])->assertSessionHasErrors('phrase');
    expect(Setting::where('group', 'restore')->count())->toBe(0)->and(Backup::where('trigger', 'safety')->count())->toBe(0);

    $limited = User::factory()->create()->assignRole(Role::Counselor->value);
    $limited->givePermissionTo(['area.admin', 'backups.manage']);
    $this->actingAs($limited)->post($url, ['password' => 'password', 'phrase' => 'RESTORE'])->assertForbidden();
    $this->actingAs($limited)->get(route('admin.system.backups.index'))->assertInertia(fn (Assert $p) => $p->where('canRestore', false));

    $this->actingAs($this->admin)->post($url, ['password' => 'password', 'phrase' => 'RESTORE'])->assertSessionHasNoErrors();
    expect(Setting::where('group', 'restore')->where('key', 'probe')->count())->toBe(1)->and($backup->fresh()->restored_at)->not->toBeNull()
        ->and(Backup::where('trigger', 'safety')->where('status', 'done')->count())->toBe(1);
});

it('refuses a backup file that is not one of ours, or holds anything but backup statements', function () {
    $service = app(BackupService::class);

    foreach (["SELECT 1;\n", "-- RTL-BACKUP v1 mysql\nDELETE FROM users;\n", "-- RTL-BACKUP v1 mysql\nDROP DATABASE x;\n"] as $i => $content) {
        $name = "db-test-bad{$i}-".uniqid().'.sql.gz';
        $gz = gzopen(BackupService::directory().'/'.$name, 'wb');
        gzwrite($gz, $content);
        gzclose($gz);
        $backup = Backup::create(['type' => 'database', 'trigger' => 'manual', 'filename' => $name, 'size' => 10, 'status' => 'done']);

        expect(fn () => $service->restore($backup))->toThrow(RuntimeException::class);
    }

    expect(User::count())->toBeGreaterThan(0);
});

it('runs from the command line and deletes old automatic backups but keeps the newest', function () {
    app(SettingsRepository::class)->set('backups.include_files', false);
    app(SettingsRepository::class)->set('backups.retention_days', 7);

    $this->artisan('backup:run --scheduled')->assertSuccessful();
    expect(Backup::count())->toBe(0); // schedule is off

    app(SettingsRepository::class)->set('backups.schedule', 'daily');
    $this->artisan('backup:run --scheduled')->assertSuccessful();
    expect(Backup::where('trigger', 'scheduled')->where('status', 'done')->count())->toBe(1);

    $old = app(BackupService::class)->create('database', 'scheduled');
    Backup::whereKey(Backup::query()->oldest('id')->value('id'))->update(['created_at' => now()->subDays(30)]);
    Backup::whereKey($old->id)->update(['created_at' => now()->subDays(20)]);

    $this->artisan('backup:run --scheduled')->assertSuccessful();

    // Two stale backups were removed; the newest remain.
    expect(Backup::where('trigger', 'scheduled')->count())->toBe(1)->and(Backup::first()->created_at->isToday())->toBeTrue();
});
