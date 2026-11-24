<?php

use App\Enums\ChatKind;
use App\Enums\Role;
use App\Models\ChatConversation;
use App\Models\ChatGuest;
use App\Models\ChatMessage;
use App\Notifications\ChatMessageReceived;
use App\Support\Chat\AutoReply\AutoReplySettings;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    Notification::fake();
    $this->withCredentials();
    app(SettingsRepository::class)->set('chat_security.guest_chat_enabled', true);
    foreach (['messages_per_minute', 'conversation_messages_per_minute', 'ip_messages_per_minute'] as $limit) {
        app(SettingsRepository::class)->set("chat_security.{$limit}", 100);
    }
    app(SettingsRepository::class)->set('general.site_name', 'RTL Immigration');
    $this->admin = $this->userWithRole(Role::SuperAdmin);
    $this->counselor = $this->userWithRole(Role::Counselor);
});

function arStart($test, string $body, string $ip = '203.0.113.50')
{
    return $test->withServerVariables(['REMOTE_ADDR' => $ip])->postJson(route('chat.guest.start'), [
        'name' => 'Rafi Ahmed', 'body' => $body,
        'form_token' => Crypt::encryptString((string) (time() - 30)),
    ]);
}

function arSay($test, string $token, string $body, ?string $clientId = null)
{
    return $test->withCookie(ChatGuest::COOKIE, $token)->postJson(route('chat.guest.messages.store'), array_filter(['body' => $body, 'client_id' => $clientId]));
}

function arBots(): array
{
    return ChatMessage::where('sender_type', ChatMessage::BOT)->orderBy('id')->pluck('body')->all();
}

it('answers the first message with a matching predefined reply, with a typing delay', function () {
    $response = arStart($this, 'Hello')->assertCreated()
        ->assertJsonPath('message.sender_type', 'visitor')
        ->assertJsonPath('auto_reply.message.sender_type', 'bot')
        ->assertJsonPath('auto_reply.message.automated', true)
        ->assertJsonPath('auto_reply.message.sender_name', 'RTL Assistant')
        ->assertJsonPath('auto_reply.message.body', 'Hello! Welcome to RTL Immigration. How can we help you today?');

    expect($response->json('auto_reply.typing_ms'))->toBeGreaterThanOrEqual(1200)->toBeLessThanOrEqual(3200)
        ->and(ChatConversation::where('kind', ChatKind::Guest)->firstOrFail()->message_count)->toBe(2);
});

it('picks the most relevant reply, uses whole words only and falls back politely', function () {
    $token = (string) arStart($this, 'Hello')->assertCreated()->getCookie(ChatGuest::COOKIE)?->getValue();

    arSay($this, $token, 'What are your services?')->assertCreated()
        ->assertJsonPath('auto_reply.message.body', 'We provide immigration, visa processing, and related consultancy services.');
    arSay($this, $token, 'How can I contact you?')->assertJsonPath('auto_reply.message.body', 'You can contact our support team for assistance.');
    // "hi" inside "this" must not count as a greeting, and a long question beats a bare hello.
    arSay($this, $token, 'Hello, what are your services?')->assertJsonPath('auto_reply.message.body', 'We provide immigration, visa processing, and related consultancy services.');
    arSay($this, $token, 'xyzzy plugh')->assertJsonPath('auto_reply.message.body', AutoReplySettings::DEFAULT_FALLBACK);
    // Never the same text twice in a row: a second unmatched message gets no second fallback.
    arSay($this, $token, 'another random thing')->assertCreated()->assertJsonMissingPath('auto_reply');

    expect(arBots())->toHaveCount(5);
});

it('sends one reply per message, even when the message is retried', function () {
    $token = (string) arStart($this, 'Hello')->getCookie(ChatGuest::COOKIE)?->getValue();
    $id = (string) str()->uuid();

    arSay($this, $token, 'Tell me about your services', $id)->assertCreated()->assertJsonPath('auto_reply.message.sender_type', 'bot');
    arSay($this, $token, 'Tell me about your services', $id)->assertOk()->assertJsonMissingPath('auto_reply');

    expect(arBots())->toHaveCount(2);
});

it('stays quiet once a person from the team has replied, and when switched off', function () {
    $token = (string) arStart($this, 'Hello')->getCookie(ChatGuest::COOKIE)?->getValue();
    $conversation = ChatGuest::firstOrFail()->conversation;

    $this->actingAs($this->admin)->postJson(route('admin.chat.messages.store', $conversation), ['body' => 'Hi, this is a real adviser.'])->assertCreated();
    auth()->logout();

    arSay($this, $token, 'What are your services?')->assertCreated()->assertJsonMissingPath('auto_reply');
    expect(arBots())->toHaveCount(1);

    app(SettingsRepository::class)->set('chat_auto_reply.enabled', false);
    arStart($this, 'Hello again', '203.0.113.51')->assertCreated()->assertJsonMissingPath('auto_reply');
});

it('shows automated replies as such and never notifies anyone about them', function () {
    $token = (string) arStart($this, 'Hello')->getCookie(ChatGuest::COOKIE)?->getValue();

    $this->withCookie(ChatGuest::COOKIE, $token)->get(route('chat.guest'))->assertInertia(fn (Assert $p) => $p
        ->has('thread.messages', 2)->where('thread.messages.1.sender_type', 'bot')->where('thread.messages.1.automated', true));

    // Staff were told about the visitor's message only, not about the automatic reply.
    Notification::assertSentToTimes($this->admin, ChatMessageReceived::class, 1);
});

it('lets chat managers edit the replies, and uses the edited ones', function () {
    $rules = [['id' => '', 'name' => 'Fees', 'active' => true, 'keywords' => ['fees', 'Cost'], 'reply' => 'Our fees depend on the country. Please book a consultation.']];

    $this->actingAs($this->counselor)->get(route('admin.chat.auto-replies'))->assertForbidden();
    $this->actingAs($this->counselor)->putJson(route('admin.chat.auto-replies.update'), [])->assertForbidden();

    $this->actingAs($this->admin)->get(route('admin.chat.auto-replies'))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/chat/AutoReplies')->has('settings.rules', 6));

    $this->actingAs($this->admin)->put(route('admin.chat.auto-replies.update'), ['enabled' => true, 'assistant_name' => 'Aria', 'fallback' => 'We will reply soon.', 'rules' => [['id' => '', 'name' => 'Fees', 'active' => true, 'keywords' => [], 'reply' => 'x']]])
        ->assertSessionHasErrors('rules.0.keywords');

    $this->actingAs($this->admin)->put(route('admin.chat.auto-replies.update'), ['enabled' => true, 'assistant_name' => 'Aria', 'fallback' => 'We will reply soon.', 'rules' => $rules])
        ->assertSessionHasNoErrors();
    auth()->logout();

    expect(app(AutoReplySettings::class)->rules())->toHaveCount(1)->and(app(AutoReplySettings::class)->rules()[0]['keywords'])->toBe(['fees', 'cost']);

    arStart($this, 'How much is the cost?')->assertCreated()
        ->assertJsonPath('auto_reply.message.sender_name', 'Aria')
        ->assertJsonPath('auto_reply.message.body', 'Our fees depend on the country. Please book a consultation.');
    arStart($this, 'Hello', '203.0.113.52')->assertJsonPath('auto_reply.message.body', 'We will reply soon.');
});
