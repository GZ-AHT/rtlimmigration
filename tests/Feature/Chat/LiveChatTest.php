<?php

use App\Enums\ChatKind;
use App\Enums\ChatStatus;
use App\Enums\LeadSource;
use App\Enums\Role;
use App\Models\ChatConversation;
use App\Models\ChatWebhookLog;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\Student;
use App\Notifications\ChatActivity;
use App\Support\Chat\ChatSettings;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

const WEBHOOK_SECRET = 'whsec-test';
const API_KEY = 'tawk-api-key';

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    // Tawk.to is off by default (config/chat.php); these tests cover the kept integration.
    config(['chat.tawk.enabled' => true]);
    app(ChatSettings::class)->save([
        'enabled' => true,
        'property_id' => 'abc123def456ghi789',
        'widget_id' => 'default',
        'show_on_website' => true,
        'show_in_portal' => true,
        'portal_tags' => ['student'],
        'api_key' => API_KEY,
        'webhook_secret' => WEBHOOK_SECRET,
    ]);
});

/** Posts a payload exactly as Tawk.to does: raw JSON, signed with HMAC-SHA1. */
function tawk($test, array $payload, ?string $eventId = null, ?string $signature = null)
{
    $body = json_encode($payload);

    return $test->call('POST', route('chat.webhook'), [], [], [], array_filter([
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_TAWK_SIGNATURE' => $signature ?? hash_hmac('sha1', $body, WEBHOOK_SECRET),
        'HTTP_X_HOOK_EVENT_ID' => $eventId ?? (string) str()->uuid(),
    ]), $body);
}

function chatStart(string $chatId = 'chat-1', string $email = 'visitor@example.com'): array
{
    return [
        'event' => 'chat:start', 'chatId' => $chatId, 'time' => '2026-11-17T10:00:00.000Z',
        'message' => ['text' => 'Hi, I want to study in Canada', 'type' => 'msg', 'sender' => ['type' => 'visitor']],
        'visitor' => ['name' => 'Rafi', 'email' => $email, 'city' => 'Dhaka', 'country' => 'BD'],
        'property' => ['id' => 'abc123def456ghi789', 'name' => 'RTL'],
    ];
}

function transcript(string $chatId = 'chat-1'): array
{
    return [
        'event' => 'chat:transcript_created', 'time' => '2026-11-17T10:20:00.000Z',
        'property' => ['id' => 'abc123def456ghi789', 'name' => 'RTL'],
        'chat' => [
            'id' => $chatId,
            'visitor' => ['name' => 'Rafi', 'email' => 'visitor@example.com', 'city' => 'Dhaka', 'country' => 'BD'],
            'messages' => [
                ['sender' => ['t' => 'v'], 'type' => 'msg', 'msg' => 'Hi, I want to study in Canada', 'time' => '2026-11-17T10:00:00.000Z'],
                ['sender' => ['t' => 'a', 'n' => 'Nadia', 'id' => 'agent-1'], 'type' => 'msg', 'msg' => 'Happy to help!', 'time' => '2026-11-17T10:01:00.000Z',
                    'attchs' => [
                        ['type' => 'file', 'content' => ['file' => ['url' => 'https://tawk.link/brochure.pdf', 'name' => 'brochure.pdf']]],
                        ['type' => 'file', 'content' => ['file' => ['url' => 'javascript:alert(1)', 'name' => 'evil']]],
                    ]],
                ['sender' => ['t' => 's'], 'type' => 'msg', 'msg' => 'Chat ended', 'time' => '2026-11-17T10:20:00.000Z'],
            ],
        ],
    ];
}

function studentWithCounselor($test, string $email = 'visitor@example.com'): Student
{
    $counselor = $test->userWithRole(Role::Counselor);
    $student = Student::factory()->create(['counselor_id' => $counselor->id]);
    $student->user->update(['email' => $email]);

    return $student->fresh();
}

it('rejects webhooks with a bad or missing signature and keeps no payload', function () {
    tawk($this, chatStart(), signature: 'nope')->assertUnauthorized();

    expect(ChatConversation::count())->toBe(0)
        ->and(ChatWebhookLog::first())->signature_valid->toBeFalse()->payload->toBeNull();
});

it('rejects every webhook while no secret is set', function () {
    app(SettingsRepository::class)->set('chat.webhook_secret', null, encrypted: true);

    tawk($this, chatStart())->assertUnauthorized();
    expect(ChatWebhookLog::first()->result)->toBe('rejected: no webhook secret set');
});

it('records a chat from start to transcript', function () {
    tawk($this, chatStart())->assertOk();

    $chat = ChatConversation::firstOrFail();
    expect($chat->kind)->toBe(ChatKind::Chat)
        ->and($chat->status)->toBe(ChatStatus::Open)
        ->and($chat->visitor_email)->toBe('visitor@example.com')
        ->and($chat->first_message)->toBe('Hi, I want to study in Canada');

    tawk($this, transcript())->assertOk();

    $chat->refresh();
    expect($chat->status)->toBe(ChatStatus::Closed)
        ->and($chat->message_count)->toBe(3)
        ->and($chat->messages[1])->toMatchArray(['sender' => 'agent', 'name' => 'Nadia', 'text' => 'Happy to help!'])
        // Only https attachment links are kept.
        ->and($chat->messages[1]['attachments'])->toEqualCanonicalizing([['name' => 'brochure.pdf', 'url' => 'https://tawk.link/brochure.pdf']]);
});

it('handles events out of order and never reopens an ended chat', function () {
    tawk($this, ['event' => 'chat:end', 'chatId' => 'chat-9', 'time' => '2026-11-17T10:30:00Z', 'visitor' => ['name' => 'Late']])->assertOk();
    tawk($this, chatStart('chat-9'))->assertOk();

    expect(ChatConversation::count())->toBe(1)
        ->and(ChatConversation::first()->status)->toBe(ChatStatus::Closed);
});

it('processes a retried event only once', function () {
    tawk($this, chatStart(), 'evt-1')->assertOk();
    tawk($this, chatStart(), 'evt-1')->assertOk()->assertJson(['status' => 'duplicate']);

    expect(ChatWebhookLog::count())->toBe(1)
        ->and(ChatWebhookLog::first()->deliveries)->toBe(2)
        ->and(ChatConversation::count())->toBe(1);
});

it('links a signed-in student\'s chat to them and notifies their counselor', function () {
    Notification::fake();
    $student = studentWithCounselor($this);

    tawk($this, chatStart());

    $chat = ChatConversation::firstOrFail();
    expect($chat->student_id)->toBe($student->id)->and($chat->assigned_to)->toBe($student->counselor_id);
    Notification::assertSentTo($student->counselor, ChatActivity::class);
});

it('does not link chats by a typed email when Secure Mode is off', function () {
    app(SettingsRepository::class)->set('chat.api_key', null, encrypted: true);
    studentWithCounselor($this);

    tawk($this, chatStart());

    expect(ChatConversation::first()->student_id)->toBeNull();
});

it('records offline messages once and tells the chat managers', function () {
    Notification::fake();
    $manager = $this->userWithRole(Role::LeadManager);
    $counselor = $this->userWithRole(Role::Counselor);
    $payload = [
        'event' => 'ticket:create', 'time' => '2026-11-17T22:00:00Z',
        'requester' => ['name' => 'Mina', 'email' => 'mina@example.com'],
        'property' => ['id' => 'abc123def456ghi789', 'name' => 'RTL'],
        'ticket' => ['id' => 't-1', 'humanId' => 42, 'subject' => 'Fees?', 'message' => 'How much is the file opening fee?'],
    ];

    tawk($this, $payload)->assertOk();
    tawk($this, $payload)->assertOk();

    $msg = ChatConversation::sole();
    expect($msg->kind)->toBe(ChatKind::Offline)->and($msg->reference)->toBe('42')->and($msg->subject)->toBe('Fees?');
    Notification::assertSentTo($manager, ChatActivity::class);
    Notification::assertNotSentTo($counselor, ChatActivity::class);
});

it('shows counselors only their own and their students\' chats', function () {
    $student = studentWithCounselor($this);
    tawk($this, chatStart('mine'));
    tawk($this, chatStart('other', 'someone@example.com'));
    $mine = ChatConversation::where('external_id', 'mine')->first();
    $other = ChatConversation::where('external_id', 'other')->first();

    $this->actingAs($student->counselor)
        ->get(route('counselor.chat.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('chat/Index')->has('conversations.data', 1)->where('conversations.data.0.id', $mine->id));

    $this->get(route('counselor.chat.show', $mine))->assertOk();
    $this->get(route('counselor.chat.show', $other))->assertForbidden();
    $this->patch(route('counselor.chat.update', $mine), ['assigned_to' => null])->assertForbidden();
});

it('lets lead managers see everything and assign chats, but not change settings', function () {
    Notification::fake();
    $manager = $this->userWithRole(Role::LeadManager);
    $counselor = $this->userWithRole(Role::Counselor);
    tawk($this, chatStart());
    $chat = ChatConversation::first();

    $this->actingAs($manager)->get(route('admin.chat.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->has('conversations.data', 1));
    $this->patch(route('admin.chat.update', $chat), ['assigned_to' => (string) $counselor->id, 'tags' => ['canada', 'ielts']])->assertRedirect();

    expect($chat->fresh())->assigned_to->toBe($counselor->id)->tags->toBe(['canada', 'ielts']);
    Notification::assertSentTo($counselor, ChatActivity::class);

    $this->patch(route('admin.chat.update', $chat), ['assigned_to' => $this->userWithRole(Role::Student)->id])->assertSessionHasErrors('assigned_to');
    $this->patch(route('admin.chat.update', $chat), ['tags' => array_map(fn ($i) => "t{$i}", range(1, 11))])->assertSessionHasErrors('tags');

    $this->get(route('admin.chat.settings'))->assertForbidden();
    $this->put(route('admin.chat.settings.update'), [])->assertForbidden();
});

it('turns a chat visitor into a lead, assigned to the counselor who did it', function () {
    tawk($this, chatStart());
    $chat = ChatConversation::first();
    $counselor = $this->userWithRole(Role::Counselor);
    $chat->update(['assigned_to' => $counselor->id]);

    $this->actingAs($counselor)
        ->post(route('counselor.chat.lead', $chat), ['name' => 'Rafi', 'email' => 'visitor@example.com'])
        ->assertRedirect();

    $lead = Lead::sole();
    expect($lead->source)->toBe(LeadSource::LiveChat)
        ->and($lead->counselor_id)->toBe($counselor->id)
        ->and($chat->fresh()->lead_id)->toBe($lead->id);

    $this->post(route('counselor.chat.lead', $chat), ['name' => 'Again', 'email' => 'x@example.com'])->assertSessionHasErrors('lead');
});

it('saves settings without ever sending the secrets back', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);

    $this->actingAs($admin)->put(route('admin.chat.settings.update'), [
        'enabled' => true, 'property_id' => 'newproperty12345', 'widget_id' => 'w1',
        'show_on_website' => true, 'show_in_portal' => false, 'portal_tags' => ['portal'],
        'api_key' => '', 'webhook_secret' => '',
    ])->assertRedirect()->assertSessionHasNoErrors();

    // Blank secret fields keep the stored ones.
    expect(app(ChatSettings::class)->signatureIsValid('x', hash_hmac('sha1', 'x', WEBHOOK_SECRET)))->toBeTrue();

    $this->get(route('admin.chat.settings'))->assertOk()->assertInertia(fn (Assert $p) => $p
        ->where('settings.property_id', 'newproperty12345')
        ->where('settings.has_api_key', true)
        ->missing('settings.api_key')
        ->missing('settings.webhook_secret'));

    expect(json_encode($this->get(route('admin.chat.settings'))->original->getData()))->not->toContain(API_KEY)->not->toContain(WEBHOOK_SECRET);

    $this->put(route('admin.chat.settings.update'), [
        'enabled' => true, 'property_id' => '"><script>', 'widget_id' => 'w1',
        'show_on_website' => true, 'show_in_portal' => true, 'portal_tags' => [],
    ])->assertSessionHasErrors('property_id');
});

it('says in the delivery log whether a chat was linked to a student, and why not', function () {
    $student = studentWithCounselor($this, 'linked@example.com');

    tawk($this, chatStart('c-linked', 'linked@example.com'))->assertOk();
    tawk($this, chatStart('c-unknown', 'nobody@example.com'))->assertOk();

    expect(ChatWebhookLog::where('external_id', 'c-linked')->value('result'))->toBe("chat recorded · student #{$student->id}")
        ->and(ChatWebhookLog::where('external_id', 'c-unknown')->value('result'))->toBe('chat recorded · not linked: no student with this email');

    app(SettingsRepository::class)->set('chat.api_key', null, encrypted: true);
    tawk($this, chatStart('c-insecure', 'linked@example.com'))->assertOk();

    expect(ChatWebhookLog::where('external_id', 'c-insecure')->value('result'))->toBe('chat recorded · not linked: Secure Mode API key not saved')
        ->and(ChatConversation::where('external_id', 'c-insecure')->value('student_id'))->toBeNull();
});

it('writes webhook outcomes to the application log', function () {
    Log::spy();

    tawk($this, chatStart(), signature: 'bad')->assertUnauthorized();
    tawk($this, chatStart())->assertOk();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $m) => str_contains($m, 'signature does not match'))->once();
    Log::shouldHaveReceived('info')->withArgs(fn (string $m, array $c) => $m === 'Tawk.to webhook processed' && $c['chat'] === 'chat-1')->once();
});

function chatSettingsPayload(array $webhookUrls): array
{
    return [
        'enabled' => true, 'property_id' => 'abc123def456ghi789', 'widget_id' => 'default',
        'show_on_website' => true, 'show_in_portal' => true, 'portal_tags' => ['student'],
        'webhook_urls' => $webhookUrls,
    ];
}

it('saves a webhook address per environment, encrypted, and shows this server its own', function () {
    $this->actingAs($this->userWithRole(Role::SuperAdmin));

    // No address saved: the default route on this server.
    $this->get(route('admin.chat.settings'))->assertInertia(fn (Assert $p) => $p
        ->where('webhookUrl', route('chat.webhook'))
        ->where('environment', 'local'));

    $urls = [
        'local' => 'https://abc123.ngrok-free.app/chat/webhook/tawk',
        'staging' => 'https://staging.rtl.example.com/chat/webhook/tawk',
        'production' => 'https://rtl.example.com/chat/webhook/tawk',
    ];

    $this->put(route('admin.chat.settings.update'), chatSettingsPayload($urls))->assertSessionHasNoErrors();

    $row = Setting::query()->where('group', 'chat')->where('key', 'webhook_urls')->firstOrFail();
    expect($row->is_encrypted)->toBeTrue()
        ->and((string) $row->getRawOriginal('value'))->not->toContain('rtl.example.com');

    // The test suite runs as "testing", which uses the local address.
    $this->get(route('admin.chat.settings'))->assertInertia(fn (Assert $p) => $p
        ->where('webhookUrl', $urls['local'])
        ->where('defaultWebhookUrl', route('chat.webhook'))
        ->where('settings.webhook_urls', $urls));

    app()->detectEnvironment(fn () => 'production');
    expect(app(ChatSettings::class)->webhookUrl())->toBe($urls['production']);

    app()->detectEnvironment(fn () => 'staging');
    expect(app(ChatSettings::class)->webhookUrl())->toBe($urls['staging']);

    app()->detectEnvironment(fn () => 'testing');

    // Leaving webhook_urls out of a save keeps them; blanking one falls back to the route.
    $this->put(route('admin.chat.settings.update'), array_diff_key(chatSettingsPayload([]), ['webhook_urls' => true]))->assertSessionHasNoErrors();
    expect(app(ChatSettings::class)->webhookUrls())->toBe($urls);

    $this->put(route('admin.chat.settings.update'), chatSettingsPayload([...$urls, 'local' => '']))->assertSessionHasNoErrors();
    expect(app(ChatSettings::class)->webhookUrl())->toBe(route('chat.webhook'));

    // The route and signature check are unchanged.
    tawk($this, chatStart())->assertOk();
    tawk($this, chatStart('chat-2'), signature: 'bad')->assertUnauthorized();
});

it('rejects webhook addresses Tawk.to could not use', function (string $env, string $url) {
    $this->actingAs($this->userWithRole(Role::SuperAdmin))
        ->put(route('admin.chat.settings.update'), chatSettingsPayload([$env => $url]))
        ->assertSessionHasErrors("webhook_urls.{$env}");
})->with([
    'staging over http' => ['staging', 'http://staging.rtl.example.com/chat/webhook/tawk'],
    'production over http' => ['production', 'http://rtl.example.com/chat/webhook/tawk'],
    'production on localhost' => ['production', 'https://localhost/chat/webhook/tawk'],
    'production on a private IP' => ['production', 'https://192.168.1.10/chat/webhook/tawk'],
    'wrong path' => ['production', 'https://rtl.example.com/webhook'],
    'query string' => ['production', 'https://rtl.example.com/chat/webhook/tawk?x=1'],
    'credentials' => ['staging', 'https://user:pass@rtl.example.com/chat/webhook/tawk'],
    'not a url' => ['local', 'javascript:alert(1)'],
]);

it('accepts http and localhost addresses for local development only', function () {
    $this->actingAs($this->userWithRole(Role::SuperAdmin))
        ->put(route('admin.chat.settings.update'), chatSettingsPayload([
            'local' => 'http://127.0.0.1:8000/chat/webhook/tawk',
            'production' => 'https://rtl.example.com/app/chat/webhook/tawk/',
        ]))->assertSessionHasNoErrors();

    expect(app(ChatSettings::class)->webhookUrl())->toBe('http://127.0.0.1:8000/chat/webhook/tawk');
});

it('gives the widget to visitors and students with a server-made hash, never to staff', function () {
    $this->get(route('contact'))->assertInertia(fn (Assert $p) => $p
        ->where('chat.propertyId', 'abc123def456ghi789')
        ->where('chat.visitor', null));

    $student = studentWithCounselor($this, 'stu@example.com');
    $this->actingAs($student->user)->get(route('portal.chat'))->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('portal/Chat')
        ->where('available', true)
        ->where('chat.visitor.email', 'stu@example.com')
        ->where('chat.visitor.hash', hash_hmac('sha256', 'stu@example.com', API_KEY))
        ->where('chat.attributes.student-no', $student->student_number)
        ->where('chat.tags', ['student']));

    $this->actingAs($student->counselor)->get(route('counselor.chat.index'))->assertInertia(fn (Assert $p) => $p->where('chat', null));
});

it('hides the widget when live chat is switched off', function () {
    app(SettingsRepository::class)->set('chat.enabled', false);

    $this->get(route('contact'))->assertInertia(fn (Assert $p) => $p->where('chat', null));
});
