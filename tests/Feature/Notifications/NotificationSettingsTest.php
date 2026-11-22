<?php

use App\Enums\Role;
use App\Notifications\AppNotification;
use App\Notifications\SecurityAlert;
use App\Support\Notifications\NotificationCatalog;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Notifications\SafeMailChannel;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->admin = $this->userWithRole(Role::SuperAdmin);
    $this->student = $this->userWithRole(Role::Student);
    $this->prefs = app(NotificationPreferences::class);
});

/** Every field the settings screen posts, with one role default changed. */
function settingsPayload(array $roles = [], array $templates = []): array
{
    return [
        'roles' => $roles ?: ['student' => ['payment' => ['in_app' => true, 'email' => true]]],
        'templates' => $templates ?: [SecurityAlert::class => ['in_app' => true, 'email' => true, 'email_subject' => '', 'email_body' => '']],
    ];
}

it('lists every notification type in the catalog', function () {
    $missing = [];

    foreach (glob(app_path('Notifications/*.php')) as $file) {
        $class = 'App\\Notifications\\'.basename($file, '.php');

        if (is_subclass_of($class, AppNotification::class) && ! (new ReflectionClass($class))->isAbstract() && ! isset(NotificationCatalog::TYPES[$class])) {
            $missing[] = $class;
        }
    }

    expect($missing)->toBe([])
        ->and(collect(NotificationCatalog::TYPES)->pluck('category')->unique()->diff(array_keys(NotificationCatalog::CATEGORIES))->all())->toBe([]);
});

describe('delivery channels', function () {
    it('is in-app only until someone asks for email', function () {
        expect($this->prefs->channels($this->student, SecurityAlert::class, 'payment'))->toBe(['database']);
    });

    it('follows the role default, then the person\'s own choice', function () {
        $this->prefs->save(['roles' => ['student' => ['payment' => ['in_app' => true, 'email' => true]]], 'templates' => []]);

        expect($this->prefs->channels($this->student, 'x', 'payment'))->toBe(['database', SafeMailChannel::class])
            ->and($this->prefs->channels($this->student, 'x', 'student'))->toBe(['database']);

        $this->student->forceFill(['notification_preferences' => ['payment' => ['in_app' => false, 'email' => false]]])->save();

        expect($this->prefs->channels($this->student->fresh(), 'x', 'payment'))->toBe([]);
    });

    it('gives someone with two roles the most generous setting', function () {
        $this->prefs->save(['roles' => ['student' => ['payment' => ['in_app' => false, 'email' => false]], 'counselor' => ['payment' => ['in_app' => true, 'email' => true]]], 'templates' => []]);
        $both = $this->userWithRole(Role::Student)->assignRole(Role::Counselor->value);

        expect($this->prefs->channels($both->fresh(), 'x', 'payment'))->toBe(['database', SafeMailChannel::class]);
    });

    it('lets a template switch one type off for a channel but never a mandatory one', function () {
        $this->prefs->save(['roles' => ['student' => ['system' => ['in_app' => true, 'email' => true]]], 'templates' => [
            SecurityAlert::class => ['in_app' => false, 'email' => false, 'email_subject' => '', 'email_body' => ''],
        ]]);

        expect($this->prefs->channels($this->student, SecurityAlert::class, 'system'))->toBe(['database']);

        $this->student->forceFill(['notification_preferences' => ['system' => ['in_app' => false, 'email' => false]]])->save();

        expect($this->prefs->channels($this->student->fresh(), SecurityAlert::class, 'system'))->toBe(['database']);
    });

    it('stores the in-app copy of a notification only when it is switched on', function () {
        $this->student->notify(new SecurityAlert('Locked out', 'Too many attempts'));
        expect($this->student->notifications()->count())->toBe(1);

        $this->student->forceFill(['notification_preferences' => ['system' => ['in_app' => false, 'email' => false]]])->save();
        $this->student->fresh()->notify(new SecurityAlert('Again', 'Still mandatory'));
        expect($this->student->notifications()->count())->toBe(2);
    });
});

describe('email copy', function () {
    it('uses the template wording and placeholders', function () {
        $this->prefs->save(['roles' => [], 'templates' => [SecurityAlert::class => [
            'in_app' => true, 'email' => true, 'email_subject' => '[{app}] {title}', 'email_body' => "Hi {name}\n\n{body}",
        ]]]);
        config(['app.name' => 'RTL']);

        $mail = (new SecurityAlert('Locked out', 'Too many attempts'))->toMail($this->student);

        expect($mail->subject)->toBe('[RTL] Locked out')
            ->and($mail->introLines)->toBe(['Hi '.$this->student->name, 'Too many attempts']);
    });

    it('falls back to the notification\'s own text', function () {
        $mail = (new SecurityAlert('Locked out', 'Too many attempts'))->toMail($this->student);

        expect($mail->subject)->toBe('Locked out')->and($mail->introLines)->toBe(['Too many attempts']);
    });

    it('never lets a mail failure break the action that caused it', function () {
        $this->mock(MailChannel::class)->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP is down'));

        app(SafeMailChannel::class)->send($this->student, new SecurityAlert('Locked out', 'x'));

        expect(true)->toBeTrue();
    });
});

describe('admin settings', function () {
    it('is limited to people who manage notifications', function () {
        $this->actingAs($this->userWithRole(Role::LeadManager))->get(route('admin.notifications.settings'))->assertForbidden();
        $this->actingAs($this->userWithRole(Role::LeadManager))->put(route('admin.notifications.settings.update'), settingsPayload())->assertForbidden();
        $this->actingAs($this->student)->get('/admin/notifications/settings')->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.notifications.settings'))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/notifications/Settings')
                ->has('roles', 5)->has('categories', 6)->has('templates', count(NotificationCatalog::TYPES)));
    });

    it('saves role defaults and templates and records the change', function () {
        $this->actingAs($this->admin)->put(route('admin.notifications.settings.update'), settingsPayload(
            ['student' => ['payment' => ['in_app' => true, 'email' => true]]],
            [SecurityAlert::class => ['in_app' => false, 'email' => true, 'email_subject' => ' Alert ', 'email_body' => 'Details']],
        ))->assertRedirect();

        expect($this->prefs->roleDefault(['student'], 'payment'))->toBe(['in_app' => true, 'email' => true]);
        $template = $this->prefs->template(SecurityAlert::class);
        expect($template['email_subject'])->toBe('Alert')
            ->and($template['in_app'])->toBeTrue()  // mandatory types stay on in-app
            ->and(Activity::query()->where('description', 'Updated notification templates and settings')->exists())->toBeTrue();
    });

    it('refuses unknown roles, categories and types', function () {
        $url = route('admin.notifications.settings.update');
        $row = ['in_app' => true, 'email' => false];

        $this->actingAs($this->admin)->put($url, settingsPayload(['wizard' => ['payment' => $row]]))->assertStatus(422);
        $this->actingAs($this->admin)->put($url, settingsPayload(['student' => ['gossip' => $row]]))->assertStatus(422);
        $this->actingAs($this->admin)->put($url, settingsPayload(templates: ['App\\Nope' => $row + ['email_subject' => '', 'email_body' => '']]))->assertStatus(422);
        $this->actingAs($this->admin)->put($url, ['roles' => ['student' => ['payment' => ['in_app' => 'maybe', 'email' => true]]], 'templates' => settingsPayload()['templates']])->assertSessionHasErrors('roles.student.payment.in_app');
    });
});

describe('a person\'s own choices', function () {
    it('saves, validates and resets them', function () {
        $this->actingAs($this->student)->put(route('notifications.preferences'), ['preferences' => ['payment' => ['in_app' => true, 'email' => true]]])->assertRedirect();
        expect($this->student->fresh()->notification_preferences)->toEqual(['payment' => ['in_app' => true, 'email' => true]]);

        $this->actingAs($this->student)->put(route('notifications.preferences'), ['preferences' => ['gossip' => ['in_app' => true, 'email' => true]]])->assertStatus(422);

        $this->actingAs($this->student)->put(route('notifications.preferences'), ['reset' => true])->assertRedirect();
        expect($this->student->fresh()->notification_preferences)->toBeNull();
    });

    it('cannot change anyone else\'s choices', function () {
        $other = $this->userWithRole(Role::Student);
        $this->actingAs($this->student)->put(route('notifications.preferences'), ['preferences' => ['payment' => ['in_app' => false, 'email' => false]], 'user_id' => $other->id]);

        expect($other->fresh()->notification_preferences)->toBeNull();
    });
});

it('filters the notification centre by category and counts unread per category', function () {
    $make = fn (string $category, bool $read = false) => $this->student->notifications()->create([
        'id' => (string) Str::uuid(), 'type' => 'test', 'read_at' => $read ? now() : null,
        'data' => ['title' => $category, 'body' => '', 'url' => null, 'icon' => 'info', 'category' => $category],
    ]);
    $make('payment');
    $make('payment', read: true);
    $make('student');

    $this->actingAs($this->student)->get(route('notifications.index', ['category' => 'payment']))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('notifications.data', 2)
            ->where('categories.4.key', 'payment')->where('categories.4.unread', 1)->where('categories.2.unread', 1));

    $this->actingAs($this->student)->get(route('notifications.index', ['category' => 'payment', 'filter' => 'unread']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('notifications.data', 1));

    $this->actingAs($this->student)->get(route('notifications.index', ['category' => 'gossip']))->assertSessionHasErrors('category');
});
