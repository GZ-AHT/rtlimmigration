<?php

use App\Enums\ChatKind;
use App\Enums\Role;
use App\Models\ChatConversation;
use App\Models\ChatGuest;
use App\Models\ChatMessage;
use App\Models\Media;
use App\Notifications\ChatMessageReceived;
use App\Support\Chat\AutoReply\AutoReplySettings;
use App\Support\Chat\ChatTyping;
use App\Support\Phone\PhoneNumbers;
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
        'name' => 'Rafi Ahmed', 'phone' => '01712345678', 'body' => $body,
        'form_token' => Crypt::encryptString((string) (time() - 30)),
    ]);
}

function arSay($test, string $token, string $body, ?string $clientId = null)
{
    return $test->withCookie(ChatGuest::COOKIE, $token)->postJson(route('chat.guest.messages.store'), array_filter(['body' => $body, 'client_id' => $clientId]));
}

function arHandoverSettings(array $over = []): array
{
    return ['welcome' => 'Hello! Welcome to {site_name}. How can we help you today?', 'handover_enabled' => true, 'handover_agent_name' => 'MD Hasnat', 'handover_agent_id' => null, 'handover_avatar' => null,
        'handover_message' => 'You are now connected with {agent_name}. How can I assist you?', 'handover_keywords' => ['human'], ...$over];
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

    $this->actingAs($this->admin)->put(route('admin.chat.auto-replies.update'), [...arHandoverSettings(), 'enabled' => true, 'assistant_name' => 'Aria', 'fallback' => 'We will reply soon.', 'rules' => [['id' => '', 'name' => 'Fees', 'active' => true, 'keywords' => [], 'reply' => 'x']]])
        ->assertSessionHasErrors('rules.0.keywords');

    $this->actingAs($this->admin)->put(route('admin.chat.auto-replies.update'), [...arHandoverSettings(), 'enabled' => true, 'assistant_name' => 'Aria', 'fallback' => 'We will reply soon.', 'rules' => $rules])
        ->assertSessionHasNoErrors();
    auth()->logout();

    expect(app(AutoReplySettings::class)->rules())->toHaveCount(1)->and(app(AutoReplySettings::class)->rules()[0]['keywords'])->toBe(['fees', 'cost']);

    arStart($this, 'How much is the cost?')->assertCreated()
        ->assertJsonPath('auto_reply.message.sender_name', 'Aria')
        ->assertJsonPath('auto_reply.message.body', 'Our fees depend on the country. Please book a consultation.');
    arStart($this, 'Hello', '203.0.113.52')->assertJsonPath('auto_reply.message.body', 'We will reply soon.');
});

/* ------------------------------------------------------------ phone number */

it('asks for a name and a phone number only, and stores the number in E.164', function () {
    // No email field any more: a name and a phone number, nothing else about the person.
    arStart($this, 'Hello')->assertCreated();
    $conversation = ChatConversation::where('kind', ChatKind::Guest)->firstOrFail();
    expect($conversation->visitor_phone)->toBe('+8801712345678')->and($conversation->visitor_email)->toBeNull();

    foreach (['phone', 'name'] as $missing) {
        $this->postJson(route('chat.guest.start'), array_diff_key(['name' => 'Rafi', 'phone' => '01712345678', 'body' => 'Hi', 'form_token' => Crypt::encryptString((string) (time() - 30))], [$missing => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors($missing);
    }
});

it('validates phone numbers by country and never doubles the country code', function () {
    foreach (['01712345678', '+8801712345678', '8801712345678', '+880 1712-345678', '(+880) 01712 345678'] as $input) {
        expect(PhoneNumbers::normalize($input, 'BD'))->toBe('+8801712345678');
    }

    // Bangladesh: mobile numbers only, and the right length.
    foreach (['0171234567', '017123456789', '01212345678', '12345', 'abc'] as $bad) {
        expect(PhoneNumbers::normalize($bad, 'BD'))->toBeNull();
    }

    // Other countries follow their own rules, not one fixed length.
    expect(PhoneNumbers::normalize('(415) 555-2671', 'US'))->toBe('+14155552671')
        ->and(PhoneNumbers::normalize('07911 123456', 'GB'))->toBe('+447911123456')
        ->and(PhoneNumbers::normalize('(415) 555-2671', 'GB'))->toBeNull()
        ->and(PhoneNumbers::normalize('+14155552671', 'BD'))->toBe('+14155552671');

    $response = arStart($this, 'Hello', '203.0.113.60');
    $bad = fn (string $phone, string $country) => $this->postJson(route('chat.guest.start'), ['name' => 'Rafi', 'phone' => $phone, 'country' => $country, 'body' => 'Hi', 'form_token' => Crypt::encryptString((string) (time() - 30))]);

    $bad('12345', 'BD')->assertUnprocessable()->assertJsonPath('errors.phone.0', 'Enter a valid Bangladesh phone number, for example 01812-345678.');
    $bad('01712345678', 'US')->assertUnprocessable()->assertJsonValidationErrors('phone');
    $bad('(415) 555-2671', 'US')->assertCreated();
    expect($response->status())->toBe(201)->and(ChatConversation::where('visitor_phone', '+14155552671')->exists())->toBeTrue();
});

it('lists every country with its dialing code, Bangladesh by default', function () {
    $countries = collect(PhoneNumbers::countries());
    expect($countries->count())->toBeGreaterThan(200)
        ->and($countries->firstWhere('code', 'BD'))->toMatchArray(['dial' => '+880', 'name' => 'Bangladesh', 'flag' => '🇧🇩'])
        ->and($countries->firstWhere('code', 'US')['dial'])->toBe('+1');

    $this->get(route('chat.guest.widget'))->assertOk()->assertJsonPath('defaultCountry', 'BD')->assertJsonFragment(['code' => 'GB']);
});

/* ------------------------------------------------------------ human handover */

it('hands the chat to a person when the visitor asks, and the assistant stops', function () {
    $response = arStart($this, 'I want to talk to a human please')->assertCreated()
        ->assertJsonPath('auto_reply.handover', true)
        ->assertJsonPath('auto_reply.typing_ms', 0)
        ->assertJsonPath('auto_reply.message.sender_type', 'staff')
        ->assertJsonPath('auto_reply.message.human', true)
        ->assertJsonPath('auto_reply.message.sender_name', 'MD Hasnat')
        ->assertJsonPath('auto_reply.message.body', 'You are now connected with MD Hasnat. How can I assist you?');

    $conversation = ChatConversation::where('kind', ChatKind::Guest)->firstOrFail();
    expect($conversation->handed_over_at)->not->toBeNull()->and(arBots())->toBe([]);

    $token = (string) $response->getCookie(ChatGuest::COOKIE)?->getValue();

    // From now on no automatic reply, not even to a question it could answer.
    arSay($this, $token, 'What are your services?')->assertCreated()->assertJsonMissingPath('auto_reply');
    expect(arBots())->toBe([]);

    // Asking again does not post the welcome twice.
    arSay($this, $token, 'human')->assertCreated()->assertJsonMissingPath('auto_reply');
    expect(ChatMessage::where('sender_type', 'staff')->count())->toBe(1);
});

it('uses the configured agent name, picture and assignment for the handover', function () {
    $agent = $this->userWithRole(Role::Counselor);
    $avatar = Media::create(['disk' => 'public', 'path' => 'media/hasnat.jpg', 'original_name' => 'hasnat.jpg', 'mime_type' => 'image/jpeg', 'size' => 10]);
    $settings = app(AutoReplySettings::class);
    $settings->save([...arHandoverSettings(['handover_agent_id' => $agent->id, 'handover_avatar' => $avatar->id, 'handover_agent_name' => 'Hasnat', 'handover_message' => 'Hi, {agent_name} here!']), 'enabled' => true, 'assistant_name' => 'RTL Assistant', 'fallback' => 'x', 'rules' => []]);

    arStart($this, 'speak to a human')->assertCreated()
        ->assertJsonPath('auto_reply.message.sender_name', 'Hasnat')
        ->assertJsonPath('auto_reply.message.avatar', $avatar->url())
        ->assertJsonPath('auto_reply.message.body', 'Hi, Hasnat here!');

    expect(ChatConversation::where('kind', ChatKind::Guest)->firstOrFail()->assigned_to)->toBe($agent->id);
});

it('does not hand over when switched off, and the visitor keeps getting normal replies', function () {
    app(AutoReplySettings::class)->save([...arHandoverSettings(['handover_enabled' => false]), 'enabled' => true, 'assistant_name' => 'RTL Assistant', 'fallback' => 'fallback text', 'rules' => []]);

    arStart($this, 'talk to a human')->assertCreated()->assertJsonPath('auto_reply.message.sender_type', 'bot')->assertJsonPath('auto_reply.handover', false);
    expect(ChatConversation::firstOrFail()->handed_over_at)->toBeNull();
});

it('shows the agent typing only while they type, to the visitor only', function () {
    $token = (string) arStart($this, 'talk to a human')->getCookie(ChatGuest::COOKIE)?->getValue();
    $conversation = ChatGuest::firstOrFail()->conversation;

    $poll = fn () => $this->withCookie(ChatGuest::COOKIE, $token)->getJson(route('chat.guest.messages.index', ['after' => (int) $conversation->fresh()->last_seq]));
    $poll()->assertOk()->assertJsonPath('typing', null);

    $this->actingAs($this->admin)->postJson(route('admin.chat.typing', $conversation))->assertOk();
    // A counselor who is not on this chat cannot reply to it, so cannot show as typing either.
    $this->actingAs($this->counselor)->postJson(route('counselor.chat.typing', $conversation))->assertForbidden();
    auth()->logout();

    $poll()->assertJsonPath('typing.name', ChatTyping::current($conversation)['name']);

    // Sending a reply clears it.
    $this->actingAs($this->admin)->postJson(route('admin.chat.messages.store', $conversation), ['body' => 'Hello, how can I help?'])->assertCreated();
    auth()->logout();
    $poll()->assertJsonPath('typing', null);

    // A visitor cannot make the agent look like they are typing.
    $this->withCookie(ChatGuest::COOKIE, $token)->postJson(route('admin.chat.typing', $conversation))->assertUnauthorized();
});

/* ------------------------------------------------------ start without a message */

it('starts a chat from a name and phone number alone, and opens with a greeting', function () {
    $response = $this->postJson(route('chat.guest.start'), ['name' => 'Rafi Ahmed', 'phone' => '01712345678', 'form_token' => Crypt::encryptString((string) (time() - 30))])
        ->assertCreated()
        ->assertJsonPath('message', null)
        ->assertJsonPath('auto_reply.message.sender_type', 'bot')
        ->assertJsonPath('auto_reply.message.body', 'Hello! Welcome to RTL Immigration. How can we help you today?');

    expect($response->json('auto_reply.typing_ms'))->toBeGreaterThan(0);

    $conversation = ChatConversation::where('kind', ChatKind::Guest)->firstOrFail();
    expect($conversation->visitor_phone)->toBe('+8801712345678')->and($conversation->message_count)->toBe(1)
        ->and(ChatMessage::where('sender_type', ChatMessage::VISITOR)->count())->toBe(0);

    // The visitor's first real message still reaches the team, and still gets an answer.
    $token = (string) $response->getCookie(ChatGuest::COOKIE)?->getValue();
    arSay($this, $token, 'What are your services?')->assertCreated()->assertJsonPath('auto_reply.message.sender_type', 'bot');
    Notification::assertSentToTimes($this->admin, ChatMessageReceived::class, 1);

    // The chat page opens on the greeting.
    $this->withCookie(ChatGuest::COOKIE, $token)->get(route('chat.guest'))->assertInertia(fn (Assert $p) => $p->where('thread.messages.0.sender_type', 'bot'));
});

it('starts empty when automatic replies are off, and still needs a valid name and phone', function () {
    app(SettingsRepository::class)->set('chat_auto_reply.enabled', false);
    $token = fn () => Crypt::encryptString((string) (time() - 30));

    $this->postJson(route('chat.guest.start'), ['name' => 'Rafi', 'phone' => '01712345678', 'form_token' => $token()])
        ->assertCreated()->assertJsonMissingPath('auto_reply');
    expect(ChatMessage::count())->toBe(0);

    $this->postJson(route('chat.guest.start'), ['name' => 'Rafi', 'phone' => '123', 'form_token' => $token()])->assertUnprocessable()->assertJsonValidationErrors('phone');
});
