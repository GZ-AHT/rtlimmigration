<?php

use App\Enums\ChatKind;
use App\Enums\ModerationStatus;
use App\Enums\Role;
use App\Models\ChatConversation;
use App\Models\ChatGuest;
use App\Models\ChatIpBlock;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatModerationEvent;
use App\Models\ChatSecurityEvent;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ChatMessageReceived;
use App\Notifications\ChatSecurityAlert;
use App\Support\Chat\ChatSecuritySettings;
use App\Support\Chat\Security\MalwareScanner;
use App\Support\Chat\Security\Turnstile;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as RoleModel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    Storage::fake(config('documents.disk'));
    Notification::fake();
    // These tests count messages; automatic replies have their own tests (AutoReplyTest).
    app(SettingsRepository::class)->set('chat_auto_reply.enabled', false);
    // JSON requests only carry cookies (the guest session) with credentials, like fetch(credentials: 'same-origin').
    $this->withCredentials();

    $this->counselor = $this->userWithRole(Role::Counselor);
    $this->admin = $this->userWithRole(Role::SuperAdmin);
    $this->student = Student::factory()->create(['counselor_id' => $this->counselor->id]);
});

/* ---------------------------------------------------------------- helpers */

function csSetting(string $key, mixed $value, bool $encrypted = false): void
{
    app(SettingsRepository::class)->set("chat_security.{$key}", $value, $encrypted);
}

function csLimits(array $limits): void
{
    foreach ($limits as $key => $value) {
        csSetting($key, $value);
    }
}

function csSay($test, Student $student, array $data, string $ip = '198.51.100.10')
{
    return $test->actingAs($student->user)
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson(route('portal.chat.messages.store'), $data);
}

function csThread(Student $student): ChatConversation
{
    return ChatConversation::where('kind', ChatKind::Portal)->where('external_id', ChatConversation::portalKey($student->id))->firstOrFail();
}

function csPdf(string $name = 'scan.pdf', string $extra = ''): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n{$extra}\ntrailer << /Root 1 0 R >>\n%%EOF\n");
}

/** Starts a website chat as a guest from an IP; returns the response. */
function csGuestStart($test, array $data = [], string $ip = '203.0.113.20')
{
    return $test->withServerVariables(['REMOTE_ADDR' => $ip])->postJson(route('chat.guest.start'), [
        'name' => 'Rafi Ahmed',
        'email' => 'rafi@example.com',
        'body' => 'Hello, I want to study in Canada.',
        'form_token' => Crypt::encryptString((string) (time() - 30)),
        ...$data,
    ]);
}

function csGuestToken($response): string
{
    return (string) $response->getCookie(ChatGuest::COOKIE)?->getValue();
}

/* ------------------------------------------------------- A. spam protection */

it('limits message flooding, then starts a cooldown and alerts Super Admin', function () {
    csLimits(['messages_per_minute' => 3, 'violations_before_cooldown' => 2, 'cooldown_minutes' => 10]);

    foreach (['one', 'two', 'three'] as $text) {
        csSay($this, $this->student, ['body' => $text])->assertCreated();
    }

    csSay($this, $this->student, ['body' => 'four'])->assertStatus(429)->assertHeader('Retry-After')
        ->assertJsonPath('message', fn (string $m) => str_contains($m, 'too quickly'));
    expect(ChatSecurityEvent::where('type', 'rate_limited')->count())->toBe(1);

    // The second violation within the hour starts a cooldown.
    csSay($this, $this->student, ['body' => 'five'])->assertStatus(429)->assertJsonPath('retry_after', 600);
    expect(ChatSecurityEvent::where('type', 'cooldown')->count())->toBe(1);
    Notification::assertSentTo($this->admin, ChatSecurityAlert::class);

    // During the cooldown nothing gets through, and nothing was silently stored.
    $this->travel(2)->minutes();
    csSay($this, $this->student, ['body' => 'six'])->assertStatus(429);
    expect(ChatMessage::count())->toBe(3);

    // Staff replies are not limited by the visitor limits.
    $thread = csThread($this->student);
    foreach (range(1, 5) as $i) {
        $this->actingAs($this->counselor)->postJson(route('counselor.chat.messages.store', $thread), ['body' => "Reply {$i}"])->assertCreated();
    }
});

it('refuses a duplicate submission but answers a retry of the same message', function () {
    $clientId = (string) Str::uuid();

    csSay($this, $this->student, ['body' => 'Is my visa ready?', 'client_id' => $clientId])->assertCreated();
    csSay($this, $this->student, ['body' => 'Is my visa ready?', 'client_id' => $clientId])->assertOk();
    csSay($this, $this->student, ['body' => 'is my VISA ready??', 'client_id' => (string) Str::uuid()])
        ->assertUnprocessable()->assertJsonPath('message', 'You just sent this message. It has been received.');

    expect(ChatMessage::count())->toBe(1)->and(ChatSecurityEvent::where('type', 'duplicate')->count())->toBe(1);

    // After the window it is a new message again.
    $this->travel(2)->minutes();
    csSay($this, $this->student, ['body' => 'Is my visa ready?'])->assertCreated();
});

it('holds a message repeated many times as spam', function () {
    csLimits(['repeat_threshold' => 3, 'duplicate_window_seconds' => 0]);

    csSay($this, $this->student, ['body' => 'Call me now'])->assertCreated()->assertJsonPath('message.moderation.state', 'delivered');
    csSay($this, $this->student, ['body' => 'Call me now'])->assertCreated();
    csSay($this, $this->student, ['body' => 'Call me now'])->assertCreated()->assertJsonPath('message.moderation.state', 'not_delivered');

    expect(ChatMessage::latest('id')->first()->moderation_status)->toBe(ModerationStatus::Blocked)
        ->and(ChatSecurityEvent::where('type', 'repeated_spam')->exists())->toBeTrue();
});

/* ------------------------------------------------ B. content filtering */

it('lets ordinary messages with normal links through', function () {
    csSay($this, $this->student, ['body' => "Here is the course page: https://www.ox.ac.uk/admissions/graduate\nand the embassy site www.canada.ca/en/immigration"])
        ->assertCreated()->assertJsonPath('message.moderation.state', 'delivered');

    $message = ChatMessage::firstOrFail();
    expect($message->moderation_status)->toBe(ModerationStatus::Approved)->and(ChatModerationEvent::count())->toBe(0);
    Notification::assertSentTo($this->counselor, ChatMessageReceived::class);

    $this->actingAs($this->counselor)->get(route('counselor.chat.show', csThread($this->student)))
        ->assertInertia(fn (Assert $p) => $p->where('thread.messages.0.body', $message->body));
});

it('blocks configured keywords and domains, and keeps the message for review', function () {
    csSetting('blocked_keywords', ['casino royale']);
    csSetting('blocked_domains', ['evil.example']);

    csSay($this, $this->student, ['body' => 'Join CASINO Royale tonight'])->assertCreated()
        ->assertJsonPath('message.moderation.state', 'not_delivered')
        ->assertJsonPath('message.body', 'Join CASINO Royale tonight');
    csSay($this, $this->student, ['body' => 'see login.evil.example/page'])->assertCreated();

    expect(ChatMessage::pluck('moderation_status')->all())->toBe([ModerationStatus::Blocked, ModerationStatus::Blocked])
        ->and(ChatMessage::first()->moderation_reasons)->toContain('blocked_keyword')
        ->and(ChatMessage::latest('id')->first()->moderation_reasons)->toBe(['blocked_domain'])
        ->and(ChatModerationEvent::where('to_status', 'blocked')->whereNull('user_id')->count())->toBe(2);

    // The counselor (not a moderator) sees that something is waiting, not its text, and is not notified.
    Notification::assertNotSentTo($this->counselor, ChatMessageReceived::class);
    $this->actingAs($this->counselor)->get(route('counselor.chat.show', csThread($this->student)))
        ->assertInertia(fn (Assert $p) => $p
            ->where('thread.messages.0.body', null)
            ->where('thread.messages.0.moderation.state', 'held'));

    Notification::assertSentTo($this->admin, ChatSecurityAlert::class);
});

it('flags suspicious links and scam phrases without blocking, and holds them together', function () {
    csSay($this, $this->student, ['body' => 'Check http://192.168.4.20/login please'])->assertCreated()->assertJsonPath('message.moderation.state', 'delivered');
    expect(ChatMessage::latest('id')->first()->moderation_status)->toBe(ModerationStatus::Flagged);
    expect(ChatSecurityEvent::where('type', 'suspicious_link')->first()->details)->toBe(['hosts' => ['192.168.4.20']]);

    csSay($this, $this->student, ['body' => 'Guaranteed returns! Send bitcoin to bit.ly/abc123'])->assertCreated()->assertJsonPath('message.moderation.state', 'pending');
    expect(ChatMessage::latest('id')->first()->moderation_status)->toBe(ModerationStatus::Pending);

    csSay($this, $this->student, ['body' => str_repeat('!', 30)])->assertCreated();
    expect(ChatMessage::latest('id')->first()->moderation_reasons)->toBe(['repeated_characters']);

    // Unfamiliar words are not a reason to hold anything.
    csSay($this, $this->student, ['body' => 'My SOP mentions Universität Heidelberg and the blocked account at Fintiba.'])->assertCreated();
    expect(ChatMessage::latest('id')->first()->moderation_status)->toBe(ModerationStatus::Approved);
});

/* ----------------------------------------------- E. XSS and injection */

it('blocks script payloads and never renders message text as HTML', function () {
    $payloads = ['<script>alert(1)</script>', '<img src=x onerror=alert(1)>', 'click javascript:alert(document.cookie)', '<iframe src="https://x.test"></iframe>'];

    foreach ($payloads as $i => $payload) {
        csSay($this, $this->student, ['body' => $payload." {$i}"])->assertCreated();
    }

    expect(ChatMessage::where('moderation_status', 'blocked')->count())->toBe(4);
    // Kept exactly as written (for review), not "cleaned" into something else.
    expect(ChatMessage::first()->body)->toBe('<script>alert(1)</script> 0');

    // Moderators see the text, but only ever as escaped data in the page.
    $html = $this->actingAs($this->admin)->get(route('admin.chat.moderation'))->assertOk()->getContent();
    expect($html)->not->toContain('<script>alert(1)</script>')->not->toContain('<img src=x onerror');

    // A maths symbol is not HTML.
    csSay($this, $this->student, ['body' => 'I scored <7 in writing and 8> in reading'])->assertCreated();
    expect(ChatMessage::latest('id')->first()->moderation_status)->toBe(ModerationStatus::Approved);
});

it('stores SQL injection attempts as plain text and keeps queries parameterised', function () {
    csSay($this, $this->student, ['body' => "'; DROP TABLE chat_messages; --"])->assertCreated();

    expect(Schema::hasTable('chat_messages'))->toBeTrue()
        ->and(ChatMessage::first()->body)->toBe("'; DROP TABLE chat_messages; --");

    $this->actingAs($this->admin)->get(route('admin.chat.portal', ['search' => "' OR 1=1 --"]))
        ->assertOk()->assertInertia(fn (Assert $p) => $p->has('conversations.data', 0));
    $this->get(route('admin.chat.moderation', ['search' => "%' UNION SELECT password FROM users --"]))->assertOk();
    $this->getJson(route('admin.chat.messages.index', [csThread($this->student), 'after' => '1 OR 1=1']))->assertUnprocessable();
});

/* ----------------------------------------------- D. attachment security */

it('is text-only until a Super Admin switches file types on', function () {
    csSay($this, $this->student, ['body' => 'Scan', 'files' => [csPdf()]])
        ->assertUnprocessable()->assertJsonValidationErrors('files');

    csSetting('attachment_types', ['pdf']);
    csSay($this, $this->student, ['body' => 'Scan', 'files' => [csPdf()]])->assertCreated();

    // PNG is still off.
    csSay($this, $this->student, ['body' => 'Photo', 'files' => [UploadedFile::fake()->image('photo.png')]])
        ->assertUnprocessable()->assertJsonValidationErrors('files.0');
});

it('refuses invalid and malicious files, logs them and stores nothing', function () {
    csSetting('attachment_types', ['pdf', 'jpg', 'png']);
    csSetting('max_attachment_kb', 100);
    // Refused attempts count towards the limits too; allow all seven here.
    csLimits(['messages_per_minute' => 20]);

    $photo = UploadedFile::fake()->image('a.jpg');

    $cases = [
        // Windows program renamed to .pdf
        UploadedFile::fake()->createWithContent('invoice.pdf', "MZ\x90\x00".str_repeat("\x00", 200).'This program cannot be run in DOS mode'),
        // PHP script disguised as an image
        UploadedFile::fake()->createWithContent('photo.jpg', "<?php system(\$_GET['c']); ?>"),
        // A real PNG named .pdf: the name and the contents disagree
        UploadedFile::fake()->image('scan.pdf'),
        // A PDF that runs JavaScript when opened
        csPdf('form.pdf', '2 0 obj << /S /JavaScript /JS (app.alert(1)) >> endobj'),
        // A JPEG with code hidden after the image data (polyglot)
        UploadedFile::fake()->createWithContent('selfie.jpg', file_get_contents($photo->getRealPath()).'<script>alert(1)</script>'),
        // Too big
        csPdf('big.pdf', str_repeat('A', 150 * 1024)),
        // An SVG (can carry scripts)
        UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'),
    ];

    foreach ($cases as $file) {
        csSay($this, $this->student, ['body' => 'file '.$file->getClientOriginalName(), 'files' => [$file]])->assertUnprocessable();
    }

    expect(ChatMessage::count())->toBe(0)
        ->and(ChatMessageAttachment::count())->toBe(0)
        ->and(Storage::disk(config('documents.disk'))->allFiles())->toBeEmpty()
        ->and(ChatSecurityEvent::where('type', 'malicious_attachment')->count())->toBeGreaterThanOrEqual(4);

    Notification::assertSentTo($this->admin, ChatSecurityAlert::class);
});

it('sends files to the malware scanner and refuses infected ones', function () {
    csSetting('attachment_types', ['pdf']);

    app()->instance(MalwareScanner::class, new class implements MalwareScanner
    {
        public function enabled(): bool
        {
            return true;
        }

        public function scan(string $path): ?string
        {
            return str_contains((string) file_get_contents($path), 'EICAR') ? 'Eicar-Test-Signature' : null;
        }
    });

    csSay($this, $this->student, ['body' => 'a', 'files' => [csPdf('eicar.pdf', 'EICAR')]])->assertUnprocessable();
    csSay($this, $this->student, ['body' => 'b', 'files' => [csPdf('clean.pdf')]])->assertCreated();

    expect(ChatSecurityEvent::where('type', 'malicious_attachment')->first()->details['reason'])->toBe('malware:Eicar-Test-Signature');
});

it('cleans file names and never trusts the browser', function () {
    csSetting('attachment_types', ['pdf']);

    csSay($this, $this->student, ['body' => 'x', 'files' => [csPdf("../../etc/pass\u{202E}fdp.exe<b>.pdf")]])->assertCreated();

    $attachment = ChatMessageAttachment::firstOrFail();
    expect($attachment->original_name)->not->toContain('/')->not->toContain('<')->not->toContain("\u{202E}")->toEndWith('.pdf')
        ->and($attachment->path)->toStartWith('chat-attachments/')->toMatch('~/[A-Za-z0-9]{40}\.pdf$~');
});

it('serves files of held messages only to the sender and moderators', function () {
    csSetting('attachment_types', ['pdf']);
    csSetting('blocked_keywords', ['forbidden']);

    csSay($this, $this->student, ['body' => 'forbidden offer', 'files' => [csPdf()]])->assertCreated();
    $attachment = ChatMessageAttachment::firstOrFail();

    $this->actingAs($this->student->user)->get(route('portal.chat.attachments.show', $attachment))->assertOk();
    $this->actingAs($this->counselor)->get(route('counselor.chat.attachments.show', $attachment))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.chat.attachments.show', $attachment))->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    // Unrelated students and staff never get it.
    $this->actingAs(Student::factory()->create()->user)->get(route('portal.chat.attachments.show', $attachment))->assertForbidden();
    $this->actingAs($this->userWithRole(Role::Accounts))->get(route('admin.chat.attachments.show', $attachment))->assertForbidden();
});

/* ------------------------------------------------------- F. guest chat */

it('keeps website chat off until Super Admin turns it on', function () {
    $this->get(route('chat.guest'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('website/Chat')->where('enabled', false));
    csGuestStart($this)->assertNotFound();
});

it('starts a guest chat with a secure session and isolates visitors from each other', function () {
    csSetting('guest_chat_enabled', true);

    $a = csGuestStart($this)->assertCreated()->assertJsonPath('message.sender_type', 'visitor');
    $tokenA = csGuestToken($a);
    expect(strlen($tokenA))->toBe(64);

    $guest = ChatGuest::firstOrFail();
    // Only the hash is stored; the IP is metadata, not the identity.
    expect($guest->token_hash)->toBe(hash('sha256', $tokenA))->not->toBe($tokenA)
        ->and($guest->ip_address)->toBe('203.0.113.20')
        ->and($guest->conversation->kind)->toBe(ChatKind::Guest);

    // Staff are told about the new chat (no assignee: chat managers).
    Notification::assertSentTo($this->admin, ChatMessageReceived::class);

    // Visitor A sees their chat.
    $this->withCookie(ChatGuest::COOKIE, $tokenA)->get(route('chat.guest'))->assertInertia(fn (Assert $p) => $p
        ->where('visitor.name', 'Rafi Ahmed')->has('thread.messages', 1));

    // Visitor B, from the same IP, has their own chat and cannot reach A's.
    $b = csGuestStart($this, ['name' => 'Other Person', 'body' => 'Different question about Australia']);
    $tokenB = csGuestToken($b->assertCreated());

    $this->withCookie(ChatGuest::COOKIE, $tokenB)->getJson(route('chat.guest.messages.index', ['after' => 0]))
        ->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', 'Different question about Australia');

    // No cookie, or a forged one: no access.
    $this->defaultCookies = [];
    $this->getJson(route('chat.guest.messages.index'))->assertForbidden();
    $this->withCookie(ChatGuest::COOKIE, Str::random(64))->getJson(route('chat.guest.messages.index'))->assertForbidden();

    // Staff reply reaches A only.
    $conversationA = $guest->conversation;
    $this->actingAs($this->admin)->postJson(route('admin.chat.messages.store', $conversationA), ['body' => 'Welcome! Which program?'])->assertCreated();
    auth()->logout();

    $this->withCookie(ChatGuest::COOKIE, $tokenA)->getJson(route('chat.guest.messages.index', ['after' => 1]))
        ->assertOk()->assertJsonPath('messages.0.body', 'Welcome! Which program?');
    $this->withCookie(ChatGuest::COOKIE, $tokenB)->getJson(route('chat.guest.messages.index', ['after' => 0]))
        ->assertJsonMissing(['body' => 'Welcome! Which program?']);
});

it('keeps guest attachments private to their own chat', function () {
    csSetting('guest_chat_enabled', true);
    csSetting('attachment_types', ['pdf']);

    $tokenA = csGuestToken(csGuestStart($this)->assertCreated());
    $tokenB = csGuestToken(csGuestStart($this, ['name' => 'Bea', 'body' => 'Another chat'])->assertCreated());
    $conversationA = ChatGuest::first()->conversation;

    // Visitors can never upload files, even with attachments on.
    $this->withCookie(ChatGuest::COOKIE, $tokenA)->postJson(route('chat.guest.messages.store'), ['body' => 'x', 'files' => [csPdf()]])
        ->assertUnprocessable()->assertJsonValidationErrors('files');

    $this->actingAs($this->admin)->postJson(route('admin.chat.messages.store', $conversationA), ['body' => 'Brochure', 'files' => [csPdf('brochure.pdf')]])->assertCreated();
    auth()->logout();
    $attachment = ChatMessageAttachment::firstOrFail();

    $this->withCookie(ChatGuest::COOKIE, $tokenA)->get(route('chat.guest.attachments.show', $attachment))->assertOk();
    $this->withCookie(ChatGuest::COOKIE, $tokenB)->get(route('chat.guest.attachments.show', $attachment))->assertNotFound();
});

it('stops bots with the honeypot, the form timer and the per-IP chat limit', function () {
    csSetting('guest_chat_enabled', true);
    csSetting('guest_conversations_per_hour', 2);

    csGuestStart($this, ['website' => 'http://spam.example'])->assertUnprocessable();
    expect(ChatSecurityEvent::where('type', 'honeypot')->count())->toBe(1);

    // Filled in faster than a person can (no Turnstile configured: refused).
    csGuestStart($this, ['form_token' => Crypt::encryptString((string) time())], '203.0.113.30')->assertUnprocessable();
    expect(ChatSecurityEvent::where('type', 'bot_suspected')->count())->toBe(1);

    // Tampered or expired form token.
    csGuestStart($this, ['form_token' => 'not-a-token'], '203.0.113.31')->assertUnprocessable();
    csGuestStart($this, ['form_token' => Crypt::encryptString((string) (time() - 9000))], '203.0.113.31')->assertUnprocessable();

    // A bot user agent.
    $this->withHeader('User-Agent', 'python-requests/2.31');
    csGuestStart($this, [], '203.0.113.32')->assertUnprocessable();
    $this->withHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) Firefox/131.0');

    // Two chats per hour from one address, then refused.
    csGuestStart($this, ['body' => 'first question'], '203.0.113.40')->assertCreated();
    csGuestStart($this, ['body' => 'second question'], '203.0.113.40')->assertCreated();
    csGuestStart($this, ['body' => 'third question'], '203.0.113.40')->assertStatus(429);
    expect(ChatSecurityEvent::where('type', 'conversation_limit')->count())->toBe(1);
    Notification::assertSentTo($this->admin, ChatSecurityAlert::class);

    expect(ChatConversation::where('kind', ChatKind::Guest)->count())->toBe(2);
});

it('verifies Cloudflare Turnstile on the server when CAPTCHA is required', function () {
    csSetting('guest_chat_enabled', true);
    csSetting('captcha_mode', 'always');
    csSetting('turnstile_site_key', '1x00000000000000000000AA');
    csSetting('turnstile_secret', 'secret-key', encrypted: true);

    Http::fake([Turnstile::VERIFY_URL => Http::sequence()
        ->push(['success' => false, 'error-codes' => ['invalid-input-response']])
        ->push(['success' => true])]);

    // The page offers the widget with the public site key only.
    $this->get(route('chat.guest'))->assertInertia(fn (Assert $p) => $p
        ->where('captcha.siteKey', '1x00000000000000000000AA')->where('captcha.required', true)
        ->missing('captcha.secret'));

    csGuestStart($this)->assertUnprocessable()->assertJsonPath('captcha_required', true);
    csGuestStart($this, ['captcha_token' => 'bad-token'])->assertUnprocessable()->assertJsonPath('captcha_required', true);
    expect(ChatSecurityEvent::where('type', 'captcha_failed')->count())->toBe(1);

    csGuestStart($this, ['captcha_token' => 'good-token'])->assertCreated();

    Http::assertSent(fn ($request) => $request->url() === Turnstile::VERIFY_URL
        && $request['secret'] === 'secret-key' && $request['response'] === 'good-token' && $request['remoteip'] === '203.0.113.20');
});

it('asks a suspicious guest for a CAPTCHA before their next message', function () {
    csSetting('guest_chat_enabled', true);
    csSetting('turnstile_site_key', 'site');
    csSetting('turnstile_secret', 'secret', encrypted: true);
    csSetting('blocked_keywords', ['scam offer']);
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);

    $token = csGuestToken(csGuestStart($this)->assertCreated());

    // A blocked message marks the visitor as suspicious.
    $this->withCookie(ChatGuest::COOKIE, $token)->postJson(route('chat.guest.messages.store'), ['body' => 'scam offer here'])->assertCreated();
    $this->withCookie(ChatGuest::COOKIE, $token)->postJson(route('chat.guest.messages.store'), ['body' => 'normal question'])
        ->assertUnprocessable()->assertJsonPath('captcha_required', true);
    $this->withCookie(ChatGuest::COOKIE, $token)->postJson(route('chat.guest.messages.store'), ['body' => 'normal question', 'captcha_token' => 'ok'])
        ->assertCreated();
});

it('links a guest chat to a student account only with both the chat cookie and the login', function () {
    csSetting('guest_chat_enabled', true);
    $token = csGuestToken(csGuestStart($this)->assertCreated());
    $conversation = ChatGuest::firstOrFail()->conversation;

    // Signed in, but without the chat's cookie: nothing to link.
    $this->actingAs($this->student->user)->post(route('chat.guest.link'))->assertNotFound();

    $this->withCookie(ChatGuest::COOKIE, $token)->post(route('chat.guest.link'))->assertRedirect(route('portal.chat'));

    expect($conversation->fresh()->student_id)->toBe($this->student->id)
        ->and($conversation->fresh()->assigned_to)->toBe($this->counselor->id)
        ->and(ChatGuest::first()->linked_user_id)->toBe($this->student->user_id);

    // Another student cannot take it over with the same cookie.
    $this->actingAs(Student::factory()->create()->user)->withCookie(ChatGuest::COOKIE, $token)->post(route('chat.guest.link'))->assertStatus(409);
});

/* ------------------------------------------- conversation access (IDOR) */

it('keeps website chats to the right staff and hides IP addresses from them', function () {
    csSetting('guest_chat_enabled', true);
    csGuestStart($this)->assertCreated();
    $conversation = ChatConversation::where('kind', ChatKind::Guest)->firstOrFail();

    $this->actingAs($this->counselor)->get(route('counselor.chat.show', $conversation))->assertForbidden();
    $this->actingAs($this->counselor)->postJson(route('counselor.chat.messages.store', $conversation), ['body' => 'hi'])->assertForbidden();
    $this->actingAs($this->student->user)->get(route('admin.chat.show', $conversation))->assertForbidden();

    $lead = $this->userWithRole(Role::LeadManager);
    $response = $this->actingAs($lead)->get(route('admin.chat.show', $conversation))->assertOk()->assertInertia(fn (Assert $p) => $p->where('can.reply', true));
    expect($response->getContent())->not->toContain('203.0.113.20');

    // Once assigned, the counselor can answer.
    $conversation->update(['assigned_to' => $this->counselor->id]);
    $this->actingAs($this->counselor)->postJson(route('counselor.chat.messages.store', $conversation), ['body' => 'Happy to help'])->assertCreated();
});

/* ----------------------------------------- IP blocks and cooldowns */

it('lets Super Admin block and unblock an IP address', function () {
    csSetting('guest_chat_enabled', true);

    $this->actingAs($this->admin)->post(route('admin.chat.security.blocks.store'), ['ip' => '198.51.100.10', 'reason' => 'Abuse', 'hours' => 24])->assertRedirect();
    expect(ChatIpBlock::first()->expires_at->isFuture())->toBeTrue();

    csSay($this, $this->student, ['body' => 'Hello'])->assertForbidden();
    auth()->logout();
    csGuestStart($this, [], '198.51.100.10')->assertForbidden();
    expect(ChatSecurityEvent::where('type', 'blocked_ip')->exists())->toBeTrue();

    // Another address is unaffected.
    csSay($this, $this->student, ['body' => 'Hello'], '198.51.100.11')->assertCreated();

    $this->actingAs($this->admin)->delete(route('admin.chat.security.blocks.destroy', ChatIpBlock::first()))->assertRedirect();
    csSay($this, $this->student, ['body' => 'Hello again'])->assertCreated();
});

it('suspends a flooding guest and blocks their IP automatically', function () {
    csSetting('guest_chat_enabled', true);
    csLimits(['messages_per_minute' => 1, 'violations_before_cooldown' => 1, 'cooldown_minutes' => 1, 'auto_block_after_cooldowns' => 2, 'auto_block_hours' => 6]);

    $token = csGuestToken(csGuestStart($this, [], '203.0.113.50')->assertCreated());
    $send = fn (string $body) => $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])->withCookie(ChatGuest::COOKIE, $token)->postJson(route('chat.guest.messages.store'), ['body' => $body]);

    $send('flood 1')->assertStatus(429); // first cooldown
    $this->travel(2)->minutes();
    $send('flood 2')->assertCreated();
    $send('flood 3')->assertStatus(429); // second cooldown: suspended and blocked

    expect(ChatGuest::first()->isSuspended())->toBeTrue()
        ->and(ChatIpBlock::where('ip_address', '203.0.113.50')->where('automatic', true)->exists())->toBeTrue();

    $this->travel(2)->minutes();
    $send('still here?')->assertForbidden();
});

it('never blocks a student automatically, only cools them down', function () {
    csLimits(['messages_per_minute' => 1, 'violations_before_cooldown' => 1, 'cooldown_minutes' => 1, 'auto_block_after_cooldowns' => 1]);

    csSay($this, $this->student, ['body' => 'a'])->assertCreated();
    csSay($this, $this->student, ['body' => 'b'])->assertStatus(429);

    expect(ChatIpBlock::count())->toBe(0);
    $this->travel(2)->minutes();
    csSay($this, $this->student, ['body' => 'c'])->assertCreated();
});

/* ------------------------------------------------- C/G. moderation */

it('lets only moderators review messages, and approving delivers a held one', function () {
    csSetting('blocked_keywords', ['urgent wire']);
    csSay($this, $this->student, ['body' => 'urgent wire needed'])->assertCreated();
    $message = ChatMessage::firstOrFail();
    $thread = csThread($this->student);
    $cursor = $thread->last_seq;

    foreach ([$this->counselor, $this->userWithRole(Role::LeadManager)] as $user) {
        $this->actingAs($user)->get(route('admin.chat.moderation'))->assertForbidden();
        $this->post(route('admin.chat.moderation.decide', $message), ['decision' => 'approved'])->assertForbidden();
    }

    // Rejecting needs a reason.
    $this->actingAs($this->admin)->post(route('admin.chat.moderation.decide', $message), ['decision' => 'rejected'])->assertSessionHasErrors('note');

    $this->get(route('admin.chat.moderation'))->assertOk()->assertInertia(fn (Assert $p) => $p
        ->where('counts.queue', 1)
        ->where('messages.data.0.body', 'urgent wire needed')
        ->where('messages.data.0.reasons', ['Blocked keyword']));

    $this->post(route('admin.chat.moderation.decide', $message), ['decision' => 'approved', 'note' => 'Genuine fee question'])->assertRedirect();

    expect($message->fresh()->moderation_status)->toBe(ModerationStatus::Approved)
        ->and($message->fresh()->reviewed_by)->toBe($this->admin->id)
        ->and(ChatModerationEvent::where('chat_message_id', $message->id)->pluck('to_status')->map->value->all())->toBe(['blocked', 'approved'])
        ->and(ChatModerationEvent::latest('id')->first()->note)->toBe('Genuine fee question');

    // The counselor is notified now, and their next poll picks up the released text.
    Notification::assertSentTo($this->counselor, ChatMessageReceived::class);
    $this->actingAs($this->counselor)->getJson(route('counselor.chat.messages.index', [$thread, 'after' => $cursor]))
        ->assertOk()->assertJsonPath('messages.0.body', 'urgent wire needed')->assertJsonPath('messages.0.moderation.state', 'delivered');

    // The student only ever sees their own message as delivered now.
    $this->actingAs($this->student->user)->get(route('portal.chat'))->assertInertia(fn (Assert $p) => $p->where('thread.messages.0.moderation.state', 'delivered'));

    // Rejecting later keeps the message, with its history.
    $this->actingAs($this->admin)->post(route('admin.chat.moderation.decide', $message), ['decision' => 'rejected', 'note' => 'Changed my mind'])->assertRedirect();
    expect(ChatMessage::count())->toBe(1)->and($message->fresh()->moderation_status)->toBe(ModerationStatus::Rejected);
});

it('lets a role granted chat.moderate review messages but not change security settings', function () {
    RoleModel::findOrCreate('chat-moderator', 'web')->syncPermissions(['area.admin', 'chat.access', 'chat.moderate']);
    $moderator = User::factory()->create()->assignRole('chat-moderator');

    $this->actingAs($moderator)->get(route('admin.chat.moderation'))->assertOk();
    $this->get(route('admin.chat.security'))->assertForbidden();
    $this->put(route('admin.chat.security.update'), [])->assertForbidden();
    $this->get(route('admin.chat.security.activity'))->assertForbidden();
    $this->post(route('admin.chat.security.blocks.store'), ['ip' => '1.2.3.4', 'reason' => 'x'])->assertForbidden();
});

it('keeps global security settings to Super Admin, validated and audited', function () {
    foreach ([Role::LeadManager, Role::Counselor] as $role) {
        $user = $this->userWithRole($role);
        $this->actingAs($user)->get(route('admin.chat.security'))->assertForbidden();
        $this->put(route('admin.chat.security.update'), [])->assertForbidden();
    }

    $payload = [
        'guest_chat_enabled' => true,
        'limits' => array_map(fn (array $l) => $l[0], ChatSecuritySettings::LIMITS),
        'attachment_types' => ['pdf', 'png'],
        'blocked_keywords' => ['  Crypto Doubler ', 'crypto doubler'],
        'blocked_domains' => ['https://www.Spam.example/path'],
        'trusted_domains' => ['ox.ac.uk'],
        'captcha_mode' => 'suspicious',
        'turnstile_site_key' => 'site-key',
        'turnstile_secret' => 'top-secret',
    ];

    $this->actingAs($this->admin)->put(route('admin.chat.security.update'), [...$payload, 'limits' => [...$payload['limits'], 'messages_per_minute' => 0]])
        ->assertSessionHasErrors('limits.messages_per_minute');

    $this->put(route('admin.chat.security.update'), $payload)->assertRedirect();

    $this->get(route('admin.chat.security'))->assertInertia(fn (Assert $p) => $p
        ->where('settings.guest_chat_enabled', true)
        ->where('settings.attachment_types', ['pdf', 'png'])
        ->where('settings.blocked_keywords', ['crypto doubler'])
        ->where('settings.blocked_domains', ['spam.example'])
        ->where('settings.has_turnstile_secret', true)
        ->missing('settings.turnstile_secret'));

    expect(Activity::where('description', 'Chat security settings updated')->exists())->toBeTrue();
});

/* ------------------------------------------------- I. retention */

it('prunes old security data but keeps moderation history', function () {
    csSetting('blocked_keywords', ['spamword']);
    csSay($this, $this->student, ['body' => 'spamword'])->assertCreated();

    ChatSecurityEvent::query()->update(['created_at' => now()->subDays(100)]);
    ChatIpBlock::create(['ip_address' => '192.0.2.1', 'reason' => 'old', 'expires_at' => now()->subDay()]);
    $guest = ChatGuest::create(['token_hash' => hash('sha256', 'x'), 'name' => 'Old', 'ip_address' => '192.0.2.2', 'user_agent' => 'UA', 'last_seen_at' => now()->subDays(60)]);

    $this->artisan('chat:prune-security')->assertSuccessful();

    expect(ChatSecurityEvent::count())->toBe(0)
        ->and(ChatIpBlock::count())->toBe(0)
        ->and($guest->fresh()->ip_address)->toBeNull()
        ->and($guest->fresh()->token_hash)->not->toBe(hash('sha256', 'x'))
        ->and(ChatModerationEvent::count())->toBe(1);
});
