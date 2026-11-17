<?php

use App\Actions\Chat\PortalMessaging;
use App\Actions\Students\AssignStudentCounselor;
use App\Enums\ChatKind;
use App\Enums\Role;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\Student;
use App\Notifications\ChatMessageReceived;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    Storage::fake(config('documents.disk'));
    Notification::fake();

    $this->counselor = $this->userWithRole(Role::Counselor);
    $this->student = Student::factory()->create(['counselor_id' => $this->counselor->id]);
});

function studentSays($test, Student $student, array $data)
{
    return $test->actingAs($student->user)->postJson(route('portal.chat.messages.store'), $data);
}

function thread(Student $student): ChatConversation
{
    return ChatConversation::where('kind', ChatKind::Portal)->where('external_id', ChatConversation::portalKey($student->id))->firstOrFail();
}

/** Chat is text-only until a Super Admin switches file types on. */
function allowChatFiles(array $types = ['pdf', 'jpg', 'png']): void
{
    app(SettingsRepository::class)->set('chat_security.attachment_types', $types);
}

function fakePdf(string $name = 'passport.pdf', string $extra = ''): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n{$extra}\ntrailer << /Root 1 0 R >>\n%%EOF\n");
}

it('lets a student write, the counselor read and reply, and the student see the reply', function () {
    studentSays($this, $this->student, ['body' => 'When is my visa interview?', 'client_id' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('message.sender_type', 'student')
        ->assertJsonPath('message.body', 'When is my visa interview?');

    $thread = thread($this->student);
    expect($thread->assigned_to)->toBe($this->counselor->id)
        ->and($thread->student_id)->toBe($this->student->id)
        ->and($thread->message_count)->toBe(1);

    Notification::assertSentTo($this->counselor, ChatMessageReceived::class);

    // The counselor sees it unread in the list, then opens it.
    $this->actingAs($this->counselor)->get(route('counselor.chat.portal'))->assertOk()->assertInertia(fn (Assert $p) => $p
        ->where('kind', 'portal')
        ->where('conversations.data.0.id', $thread->id)
        ->where('conversations.data.0.unread_count', 1));

    $this->get(route('counselor.chat.show', $thread))->assertOk()->assertInertia(fn (Assert $p) => $p
        ->where('can.reply', true)
        ->where('thread.messages.0.body', 'When is my visa interview?'));

    $this->get(route('counselor.chat.portal'))->assertInertia(fn (Assert $p) => $p->where('conversations.data.0.unread_count', 0));

    $this->postJson(route('counselor.chat.messages.store', $thread), ['body' => "Next Tuesday.\nBring your passport.", 'client_id' => (string) Str::uuid()])
        ->assertCreated()->assertJsonPath('message.sender_type', 'staff');

    Notification::assertSentTo($this->student->user, ChatMessageReceived::class);

    // The student's poll picks up the reply (the cursor is the conversation's change sequence).
    $firstSeq = (int) ChatMessage::orderBy('id')->value('seq');
    $this->actingAs($this->student->user)->getJson(route('portal.chat.messages.index', ['after' => $firstSeq]))
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.body', "Next Tuesday.\nBring your passport.")
        ->assertJsonPath('messages.0.sender_name', $this->counselor->name);

    $this->get(route('portal.chat'))->assertOk()->assertInertia(fn (Assert $p) => $p
        ->has('thread.messages', 2)
        ->where('thread.messages.0.sender_type', 'student')
        ->where('thread.messages.1.sender_type', 'staff'));
});

it('keeps messages in order and answers an idle poll without loading messages', function () {
    foreach (['one', 'two', 'three'] as $text) {
        studentSays($this, $this->student, ['body' => $text])->assertCreated();
    }

    $thread = thread($this->student);
    $this->actingAs($this->counselor)->postJson(route('counselor.chat.messages.store', $thread), ['body' => 'four'])->assertCreated();

    $this->getJson(route('counselor.chat.messages.index', [$thread, 'after' => 0]))
        ->assertOk()
        ->assertJsonPath('messages.*.body', ['one', 'two', 'three', 'four']);

    $thread->refresh();
    DB::enableQueryLog();
    $this->getJson(route('counselor.chat.messages.index', [$thread, 'after' => $thread->last_seq]))
        ->assertOk()->assertJsonCount(0, 'messages')->assertJsonPath('cursor', $thread->last_seq);

    $queries = collect(DB::getQueryLog())->pluck('query');
    expect($queries->filter(fn (string $q) => str_contains($q, 'chat_messages')))->toBeEmpty();
});

it('stores a retried message once', function () {
    $clientId = (string) Str::uuid();

    $first = studentSays($this, $this->student, ['body' => 'Hello', 'client_id' => $clientId])->assertCreated();
    $retry = studentSays($this, $this->student, ['body' => 'Hello', 'client_id' => $clientId])->assertOk();

    expect($retry->json('message.id'))->toBe($first->json('message.id'))
        ->and(ChatMessage::count())->toBe(1)
        ->and(thread($this->student)->message_count)->toBe(1);
});

it('keeps one thread per student', function () {
    studentSays($this, $this->student, ['body' => 'a'])->assertCreated();
    studentSays($this, $this->student, ['body' => 'b'])->assertCreated();
    app(PortalMessaging::class)->threadFor($this->student);

    expect(ChatConversation::where('kind', ChatKind::Portal)->count())->toBe(1);
});

it('stores attachments privately and only serves them to people allowed to see the thread', function () {
    allowChatFiles();

    studentSays($this, $this->student, [
        'body' => 'My passport',
        'files' => [fakePdf()],
    ])->assertCreated()->assertJsonPath('message.attachments.0.name', 'passport.pdf');

    $attachment = ChatMessageAttachment::firstOrFail();
    Storage::disk(config('documents.disk'))->assertExists($attachment->path);
    // Stored under a random name with the extension read from the contents.
    expect(basename($attachment->path))->toMatch('/^[A-Za-z0-9]{40}\.pdf$/')->and($attachment->mime_type)->toBe('application/pdf');

    $this->actingAs($this->student->user)->get(route('portal.chat.attachments.show', $attachment))->assertOk();
    $this->actingAs($this->counselor)->get(route('counselor.chat.attachments.show', $attachment))->assertOk();
    $this->actingAs($this->userWithRole(Role::SuperAdmin))->get(route('admin.chat.attachments.show', $attachment))->assertOk();

    $other = Student::factory()->create();
    $this->actingAs($other->user)->get(route('portal.chat.attachments.show', $attachment))->assertForbidden();
    $this->actingAs($this->userWithRole(Role::Counselor))->get(route('counselor.chat.attachments.show', $attachment))->assertForbidden();
});

it('refuses empty messages, unsafe files and too many files, and stores nothing', function () {
    allowChatFiles();

    studentSays($this, $this->student, ['body' => '   '])->assertUnprocessable()->assertJsonValidationErrors('body');
    studentSays($this, $this->student, ['files' => [UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')]])
        ->assertUnprocessable()->assertJsonValidationErrors('files.0');
    studentSays($this, $this->student, ['body' => 'x', 'files' => array_map(fn ($i) => fakePdf("f{$i}.pdf"), range(1, 6))])
        ->assertUnprocessable()->assertJsonValidationErrors('files');
    studentSays($this, $this->student, ['body' => str_repeat('a', PortalMessaging::MAX_BODY + 1)])->assertUnprocessable();

    expect(ChatMessage::count())->toBe(0)->and(ChatMessageAttachment::count())->toBe(0);
    expect(Storage::disk(config('documents.disk'))->allFiles())->toBeEmpty();
});

it('enforces who can read and reply', function () {
    studentSays($this, $this->student, ['body' => 'Private question'])->assertCreated();
    $thread = thread($this->student);

    // Another counselor, accounts staff and other students are kept out.
    $this->actingAs($this->userWithRole(Role::Counselor))->get(route('counselor.chat.show', $thread))->assertForbidden();
    $this->getJson(route('counselor.chat.messages.index', [$thread, 'after' => 0]))->assertForbidden();
    $this->postJson(route('counselor.chat.messages.store', $thread), ['body' => 'hi'])->assertForbidden();
    $this->actingAs($this->userWithRole(Role::Accounts))->get(route('admin.chat.show', $thread))->assertForbidden();

    $other = Student::factory()->create();
    $this->actingAs($other->user)->getJson(route('portal.chat.messages.index', ['after' => 0]))->assertOk()->assertJsonCount(0, 'messages');
    $this->get(route('counselor.chat.show', $thread))->assertForbidden();

    // Lead managers and super admins can read and reply to every thread.
    foreach ([Role::LeadManager, Role::SuperAdmin] as $role) {
        $this->actingAs($this->userWithRole($role))->postJson(route('admin.chat.messages.store', $thread), ['body' => "From {$role->value}"])->assertCreated();
    }

    // Tawk.to chats are answered in Tawk.to, never through this endpoint.
    $tawk = ChatConversation::create(['kind' => ChatKind::Chat, 'external_id' => 'tawk-1', 'student_id' => $this->student->id]);
    $this->actingAs($this->userWithRole(Role::SuperAdmin))->postJson(route('admin.chat.messages.store', $tawk), ['body' => 'x'])->assertNotFound();
});

it('moves the thread to a new counselor and keeps the history', function () {
    allowChatFiles();

    studentSays($this, $this->student, ['body' => 'Before the change', 'files' => [UploadedFile::fake()->image('photo.jpg')]])->assertCreated();
    $thread = thread($this->student);

    $newCounselor = $this->userWithRole(Role::Counselor);
    app(AssignStudentCounselor::class)($this->student->fresh(), $newCounselor, $this->userWithRole(Role::SuperAdmin));

    $thread->refresh();
    expect($thread->assigned_to)->toBe($newCounselor->id)
        ->and(ChatMessage::where('sender_type', ChatMessage::SYSTEM)->value('body'))->toContain($newCounselor->name)
        ->and($this->student->fresh()->counselorAssignments()->count())->toBeGreaterThanOrEqual(1);

    $this->actingAs($newCounselor)->get(route('counselor.chat.show', $thread))->assertOk()->assertInertia(fn (Assert $p) => $p
        ->where('thread.messages.0.body', 'Before the change')
        ->has('thread.messages.0.attachments', 1)
        ->where('thread.messages.1.sender_type', 'system'));

    // The previous counselor no longer has access.
    $this->actingAs($this->counselor)->get(route('counselor.chat.show', $thread))->assertForbidden();
});

it('notifies once for a burst of unread messages', function () {
    studentSays($this, $this->student, ['body' => 'one'])->assertCreated();
    studentSays($this, $this->student, ['body' => 'two'])->assertCreated();

    Notification::assertSentToTimes($this->counselor, ChatMessageReceived::class, 1);

    // After reading, the next message notifies again.
    $this->actingAs($this->counselor)->get(route('counselor.chat.show', thread($this->student)))->assertOk();
    studentSays($this, $this->student, ['body' => 'three'])->assertCreated();

    Notification::assertSentToTimes($this->counselor, ChatMessageReceived::class, 2);
});

it('lets staff tag a thread with topics and mark it resolved; the student writing reopens it', function () {
    studentSays($this, $this->student, ['body' => 'Payment question'])->assertCreated();
    $thread = thread($this->student);

    $this->actingAs($this->counselor)->patch(route('counselor.chat.update', $thread), ['tags' => ['Payment', 'Visa'], 'status' => 'closed'])->assertRedirect();
    expect($thread->fresh()->tags)->toBe(['Payment', 'Visa'])->and($thread->fresh()->status->value)->toBe('closed');

    $this->get(route('counselor.chat.portal', ['tag' => 'Payment']))->assertInertia(fn (Assert $p) => $p->has('conversations.data', 1));
    $this->get(route('counselor.chat.portal', ['tag' => 'Documents']))->assertInertia(fn (Assert $p) => $p->has('conversations.data', 0));

    studentSays($this, $this->student, ['body' => 'One more thing'])->assertCreated();
    expect($thread->fresh()->status->value)->toBe('open');
});
