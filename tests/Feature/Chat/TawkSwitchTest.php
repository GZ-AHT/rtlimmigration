<?php

use App\Enums\Role;
use App\Models\Student;
use App\Support\Chat\ChatSettings;
use App\Support\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Even fully configured, Tawk.to stays off until TAWK_ENABLED=true.
    app(ChatSettings::class)->save([
        'enabled' => true, 'property_id' => 'abc123def456ghi789', 'widget_id' => 'default',
        'show_on_website' => true, 'show_in_portal' => true, 'portal_tags' => [],
        'api_key' => 'key', 'webhook_secret' => 'secret',
    ]);
});

function navTitlesFor($response): array
{
    $titles = [];
    foreach ($response->viewData('page')['props']['navigation'] as $group) {
        foreach ($group['items'] as $item) {
            $titles[] = $item['title'];
            foreach ($item['children'] ?? [] as $child) {
                $titles[] = $child['title'];
            }
        }
    }

    return $titles;
}

it('keeps Tawk.to switched off by default: no widget, webhook, settings or history screens', function () {
    expect(config('chat.tawk.enabled'))->toBeFalse();

    $this->get(route('contact'))->assertInertia(fn (Assert $p) => $p->where('chat', null));
    $this->actingAs(Student::factory()->create()->user)->get(route('portal.chat'))->assertInertia(fn (Assert $p) => $p->where('chat', null));

    $this->postJson(route('chat.webhook'), ['event' => 'chat:start'])->assertNotFound();

    $admin = $this->userWithRole(Role::SuperAdmin);
    $this->actingAs($admin)->get(route('admin.chat.settings'))->assertNotFound();
    $this->get(route('admin.chat.index'))->assertNotFound();
    $this->get(route('admin.chat.offline'))->assertNotFound();

    // The system's own chat is unaffected.
    $this->get(route('admin.chat.portal'))->assertOk();
    $response = $this->get(route('admin.chat.guest'))->assertOk();

    expect(navTitlesFor($response))->toContain('Student messages', 'Website chats', 'Chat security')
        ->not->toContain('Tawk.to chat history', 'Tawk.to offline messages', 'Tawk.to settings');
});

it('shows the website chat popup to visitors and students only while website chat is on', function () {
    $this->get(route('contact'))->assertInertia(fn (Assert $p) => $p->where('guestChat', false));

    app(SettingsRepository::class)->set('chat_security.guest_chat_enabled', true);

    $this->get(route('contact'))->assertInertia(fn (Assert $p) => $p->where('guestChat', true));
    $this->actingAs(Student::factory()->create()->user)->get(route('contact'))->assertInertia(fn (Assert $p) => $p->where('guestChat', true));
    $this->actingAs($this->userWithRole(Role::Counselor))->get(route('contact'))->assertInertia(fn (Assert $p) => $p->where('guestChat', false));
});

it('gives the popup the visitor\'s chat state as JSON', function () {
    $this->getJson(route('chat.guest.widget'))->assertOk()->assertJsonPath('enabled', false);

    app(SettingsRepository::class)->set('chat_security.guest_chat_enabled', true);

    $this->getJson(route('chat.guest.widget'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('thread', null)
        ->assertJsonStructure(['visitor', 'composer', 'formToken', 'captcha', 'canLink', 'portalChatUrl']);
});
