<?php

use App\Enums\EmailAccountStatus;
use App\Enums\EmailMessageStatus;
use App\Enums\Role;
use App\Mail\OutgoingEmail;
use App\Models\EmailAccount;
use App\Models\EmailCampaign;
use App\Models\EmailMessage;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Support\Email\EmailSender;
use App\Support\Email\EmailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

const CPANEL = 'https://mail.rtl.test:2083/execute/';

function connectCpanel(): void
{
    app(EmailSettings::class)->save([
        'cpanel_host' => 'mail.rtl.test', 'cpanel_port' => 2083, 'cpanel_username' => 'rtluser', 'cpanel_token' => 'TOKEN123ABC',
        'verify_tls' => true, 'domains' => ['rtl.test'], 'default_quota_mb' => 1024, 'webmail_url' => '',
        'smtp_host' => '', 'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'imap_host' => '', 'imap_port' => 993, 'tracking_enabled' => true,
    ]);
}

/** @param array<string, mixed> $data */
function cpanelOk(mixed $data = null): array
{
    return ['status' => 1, 'errors' => null, 'messages' => null, 'data' => $data];
}

function mailbox(array $attributes = []): EmailAccount
{
    return EmailAccount::create([
        'email' => 'nadia@rtl.test', 'local_part' => 'nadia', 'domain' => 'rtl.test', 'password' => 'Secret-Pass-123',
        'status' => EmailAccountStatus::Active, 'on_server' => true, ...$attributes,
    ]);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Http::allowStrayRequests(['http://*:5173/*']);
    $this->admin = $this->userWithRole(Role::SuperAdmin);
});

it('creates a mailbox on the cPanel server and never sends its password to the browser', function () {
    connectCpanel();
    Http::fake([CPANEL.'Email/add_pop' => Http::response(cpanelOk())]);
    $counselor = $this->userWithRole(Role::Counselor);

    $this->actingAs($this->admin)->post(route('admin.email.accounts.store'), [
        'local_part' => 'Nadia', 'domain' => 'rtl.test', 'display_name' => 'Nadia', 'quota_mb' => 2048, 'users' => [$counselor->id],
    ])->assertSessionHasNoErrors();

    Http::assertSent(fn (HttpRequest $r) => $r->url() === CPANEL.'Email/add_pop'
        && $r->hasHeader('Authorization', 'cpanel rtluser:TOKEN123ABC')
        && $r['email'] === 'nadia' && $r['domain'] === 'rtl.test' && (int) $r['quota'] === 2048 && strlen($r['password']) === 24);

    $account = EmailAccount::sole();
    expect($account)->email->toBe('nadia@rtl.test')->on_server->toBeTrue()
        ->and($account->password)->toHaveLength(24)
        ->and($account->getRawOriginal('password'))->not->toContain($account->password)
        ->and($account->users->pluck('id')->all())->toBe([$counselor->id])
        ->and(Activity::where('log_name', 'email')->where('description', 'Mailbox created on the mail server')->exists())->toBeTrue();

    $this->get(route('admin.email.accounts.index'))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/email/Accounts')->where('accounts.data.0.has_password', true)->missing('accounts.data.0.password'))
        ->assertDontSee($account->password);
});

it('records a mailbox without touching any server when cPanel is not connected', function () {
    $this->actingAs($this->admin)->post(route('admin.email.accounts.store'), ['local_part' => 'info', 'domain' => 'rtl.test'])
        ->assertSessionHasNoErrors();

    Http::assertNothingSent();
    expect(EmailAccount::sole())->on_server->toBeFalse()->password->toBeNull();
});

it('shows a cPanel refusal as an error and saves nothing', function () {
    connectCpanel();
    Http::fake([CPANEL.'*' => Http::response(['status' => 0, 'errors' => ['The account “nadia@rtl.test” already exists.'], 'data' => null])]);

    $this->actingAs($this->admin)->post(route('admin.email.accounts.store'), ['local_part' => 'nadia', 'domain' => 'rtl.test'])
        ->assertSessionHasErrors(['cpanel' => 'The account “nadia@rtl.test” already exists.']);

    expect(EmailAccount::count())->toBe(0);
});

it('only allows the configured mail domains', function () {
    connectCpanel();

    $this->actingAs($this->admin)->post(route('admin.email.accounts.store'), ['local_part' => 'x', 'domain' => 'evil.test'])
        ->assertSessionHasErrors('domain');
});

it('keeps email administration to email.manage', function () {
    $counselor = $this->userWithRole(Role::Counselor);
    $manager = $this->userWithRole(Role::LeadManager);

    $this->actingAs($manager)->get(route('admin.email.accounts.index'))->assertForbidden();
    $this->actingAs($manager)->get(route('admin.email.settings'))->assertForbidden();
    $this->actingAs($manager)->get(route('admin.email.index'))->assertOk();
    $this->actingAs($counselor)->get(route('counselor.email.index'))->assertOk();
    $this->actingAs(Student::factory()->create()->user)->get(route('portal.email.index'))->assertForbidden();
});

it('saves the cPanel token encrypted and never returns it', function () {
    $this->actingAs($this->admin)->put(route('admin.email.settings.update'), [
        'cpanel_host' => 'mail.rtl.test', 'cpanel_port' => 2083, 'cpanel_username' => 'rtluser', 'cpanel_token' => 'SUPERSECRET42',
        'verify_tls' => true, 'domains' => ['RTL.test', 'rtl.test.bd'], 'default_quota_mb' => null, 'webmail_url' => '',
        'smtp_host' => '', 'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'imap_host' => '', 'imap_port' => 993, 'tracking_enabled' => false,
    ])->assertSessionHasNoErrors();

    expect(Setting::where('key', 'cpanel_token')->value('is_encrypted'))->toBeTrue()
        ->and(app(EmailSettings::class)->domains())->toBe(['rtl.test', 'rtl.test.bd']);

    $this->get(route('admin.email.settings'))->assertInertia(fn (Assert $p) => $p->where('settings.has_cpanel_token', true)->missing('settings.cpanel_token'))
        ->assertDontSee('SUPERSECRET42');
});

it('signs an assigned user in to webmail with a one-time cPanel session', function () {
    connectCpanel();
    Http::fake([CPANEL.'Session/create_webmail_session_for_mail_user' => Http::response(cpanelOk(['session' => 'nadia@rtl.test:abc123', 'token' => '/cpsess4242424242']))]);
    $counselor = $this->userWithRole(Role::Counselor);
    $account = mailbox();
    $account->users()->attach($counselor->id);

    $this->actingAs($counselor)->get(route('counselor.email.index'))
        ->assertInertia(fn (Assert $p) => $p->component('email/Index')->where('accounts.0.email', 'nadia@rtl.test')
            ->where('accounts.0.webmail_url', route('counselor.email.webmail', $account, false)));

    $this->post(route('counselor.email.webmail', $account))
        ->assertOk()
        ->assertSee('action="https://mail.rtl.test:2096/cpsess4242424242/login/"', false)
        ->assertSee('value="nadia@rtl.test:abc123"', false)
        ->assertDontSee('Secret-Pass-123')
        ->assertHeader('Cache-Control', 'no-store, private');

    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), 'create_webmail_session_for_mail_user') && $r['login'] === 'nadia' && $r['domain'] === 'rtl.test');
    expect(Activity::where('description', 'Signed in to webmail')->where('causer_id', $counselor->id)->exists())->toBeTrue();
});

it('refuses webmail for a mailbox that is not assigned to the user', function () {
    connectCpanel();
    $account = mailbox();

    $this->actingAs($this->userWithRole(Role::Counselor))->post(route('counselor.email.webmail', $account))->assertForbidden();
    Http::assertNothingSent();
});

it('sends from a counselor\'s mailbox to their own student with placeholders, escaping and signature', function () {
    Mail::fake();
    $counselor = $this->userWithRole(Role::Counselor);
    $counselor->emailSignature()->create(['body' => "Best,\nNadia"]);
    $student = Student::factory()->create(['counselor_id' => $counselor->id]);
    $student->user->update(['name' => 'Rafi Ahmed']);
    $account = mailbox();
    $account->users()->attach($counselor->id, ['can_send' => true]);
    $template = EmailTemplate::create(['name' => 'Welcome', 'slug' => 'welcome', 'subject' => 'Hello {{ first_name }}', 'body' => 'x', 'is_active' => true]);

    $this->actingAs($counselor)->post(route('counselor.email.send'), [
        'email_account_id' => $account->id,
        'recipient_type' => 'student',
        'recipient_id' => $student->id,
        'email_template_id' => $template->id,
        'subject' => 'Hello {{ first_name }}',
        'body' => "Your number is {{ student_number }}.\n<script>alert(1)</script> See https://rtl.test/guide.",
    ])->assertRedirect(route('counselor.email.index', absolute: false));

    $message = EmailMessage::sole();
    expect($message)
        ->status->toBe(EmailMessageStatus::Sent)
        ->subject->toBe('Hello Rafi')
        ->to_email->toBe($student->user->email)
        ->from_email->toBe('nadia@rtl.test')
        ->recipient_id->toBe($student->id)
        ->and($message->body)->toContain($student->student_number)
        ->toContain('&lt;script&gt;')->not->toContain('<script>')
        ->toContain('<a href="https://rtl.test/guide"')
        ->toContain("Best,<br>\nNadia");

    Mail::mailer('mailbox-'.$account->id);
    Mail::assertSent(OutgoingEmail::class, fn (OutgoingEmail $m) => $m->record->is($message));

    // REQ §3 Student Email History.
    $this->get(route('counselor.students.show', ['student' => $student, 'tab' => 'emails']))
        ->assertInertia(fn (Assert $p) => $p->where('emails.items.0.subject', 'Hello Rafi'));
});

it('does not let a counselor email another counselor\'s student or use someone else\'s mailbox', function () {
    Mail::fake();
    $counselor = $this->userWithRole(Role::Counselor);
    $other = Student::factory()->create(['counselor_id' => $this->userWithRole(Role::Counselor)->id]);
    $account = mailbox();

    $this->actingAs($counselor)->post(route('counselor.email.send'), [
        'recipient_type' => 'student', 'recipient_id' => $other->id, 'subject' => 'Hi', 'body' => 'Hi',
    ])->assertForbidden();

    $this->post(route('counselor.email.send'), [
        'email_account_id' => $account->id, 'to_email' => 'someone@example.com', 'subject' => 'Hi', 'body' => 'Hi',
    ])->assertSessionHasErrors('email_account_id');

    expect(EmailMessage::count())->toBe(0);
});

it('links a typed address to the matching student and keeps attachments private', function () {
    Mail::fake();
    Storage::fake(config('documents.disk'));
    $student = Student::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.email.send'), [
        'to_email' => $student->user->email,
        'subject' => 'Offer letter',
        'body' => 'Attached.',
        'files' => [UploadedFile::fake()->create('offer.pdf', 100, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    $message = EmailMessage::sole();
    expect($message->recipient_id)->toBe($student->id)
        ->and($message->attachments[0]['name'])->toBe('offer.pdf');
    Storage::disk(config('documents.disk'))->assertExists($message->attachments[0]['path']);
});

it('records a failed send without breaking the request', function () {
    $account = mailbox(['password' => null]);

    $this->actingAs($this->admin)->post(route('admin.email.send'), [
        'to_email' => 'someone@example.com', 'subject' => 'Hi', 'body' => 'Hi',
    ])->assertSessionHasNoErrors();
    expect(EmailMessage::sole()->status)->toBe(EmailMessageStatus::Sent);

    // An account without a password can't be chosen at all.
    $this->post(route('admin.email.send'), ['email_account_id' => $account->id, 'to_email' => 'a@example.com', 'subject' => 'Hi', 'body' => 'Hi'])
        ->assertSessionHasErrors('email_account_id');

    // ...and if it loses its password after queueing, the send is logged as failed.
    $account->update(['password' => 'Secret-Pass-123']);
    Mail::fake();
    $queued = app(EmailSender::class)->queue(['email_account_id' => $account->id, 'to_email' => 'b@example.com', 'subject' => 'S', 'body' => 'B']);
    expect($queued->fresh()->status)->toBe(EmailMessageStatus::Sent);

    $account->update(['status' => EmailAccountStatus::Suspended]);
    $failed = app(EmailSender::class)->queue(['email_account_id' => $account->id, 'to_email' => 'c@example.com', 'subject' => 'S', 'body' => 'B']);
    expect($failed->fresh())->status->toBe(EmailMessageStatus::Failed)->error->toContain('suspended');
});

it('queues one personalised email per student in a bulk send', function () {
    Mail::fake();
    $counselor = $this->userWithRole(Role::Counselor);
    $mine = Student::factory()->count(3)->create(['counselor_id' => $counselor->id]);
    Student::factory()->create();

    $this->actingAs($this->admin)->postJson(route('admin.email.bulk.preview'), ['audience' => ['type' => 'students', 'counselor' => $counselor->id]])
        ->assertJson(['count' => 3]);

    $this->post(route('admin.email.bulk.store'), [
        'name' => 'Intake reminder',
        'subject' => 'Hi {{ first_name }}',
        'body' => 'Dear {{ name }}',
        'audience' => ['type' => 'students', 'counselor' => $counselor->id],
    ])->assertSessionHasNoErrors();

    $messages = EmailMessage::orderBy('id')->get();
    $campaign = EmailCampaign::sole();
    expect($messages)->toHaveCount(3)
        ->and($messages->pluck('recipient_id')->sort()->values()->all())->toBe($mine->pluck('id')->sort()->values()->all())
        ->and($messages[0]->body)->toContain('Dear '.e($mine[0]->user->name))
        ->and($campaign)->total->toBe(3)->sent->toBe(3)->failed->toBe(0);
    Mail::assertSentCount(3);
});

it('counts opens and only follows click links it signed', function () {
    $message = EmailMessage::create([
        'uuid' => (string) str()->uuid(), 'from_email' => 'a@rtl.test', 'to_email' => 'b@example.com', 'subject' => 'S',
        'body' => '<a href="https://rtl.test/x?a=1&amp;b=2">x</a>', 'status' => EmailMessageStatus::Sent,
    ]);

    $html = (new OutgoingEmail($message))->render();
    expect($html)->toContain(route('email.track.open', $message->uuid))->not->toContain('href="https://rtl.test/x');

    $this->get(route('email.track.open', $message->uuid))->assertOk()->assertHeader('Content-Type', 'image/gif');
    $this->get(URL::signedRoute('email.track.click', ['message' => $message->uuid, 'u' => 'https://rtl.test/x?a=1&b=2']))
        ->assertRedirect('https://rtl.test/x?a=1&b=2');
    $this->get(route('email.track.click', ['message' => $message->uuid, 'u' => 'https://evil.test']))->assertForbidden();

    expect($message->fresh())->open_count->toBe(1)->click_count->toBe(1)->opened_at->not->toBeNull()->clicked_at->not->toBeNull();
});

it('syncs mailboxes, usage and suspensions from cPanel', function () {
    connectCpanel();
    $known = mailbox(['email' => 'old@rtl.test', 'local_part' => 'old']);
    $gone = mailbox(['email' => 'gone@rtl.test', 'local_part' => 'gone']);
    Http::fake([CPANEL.'Email/list_pops_with_disk' => Http::response(cpanelOk([
        ['email' => 'old@rtl.test', 'diskused' => '12.5', 'diskquota' => '1024', 'suspended_login' => 1],
        ['email' => 'new@rtl.test', 'diskused' => '0', 'diskquota' => 'unlimited', 'suspended_login' => 0],
        ['email' => 'rtluser', 'diskused' => '1', 'diskquota' => 'unlimited'],
    ]))]);

    $this->actingAs($this->admin)->post(route('admin.email.accounts.sync'))->assertSessionHasNoErrors();

    expect($known->fresh())->disk_used_mb->toBe(12.5)->quota_mb->toBe(1024)->status->toBe(EmailAccountStatus::Suspended)
        ->and($gone->fresh()->on_server)->toBeFalse()
        ->and(EmailAccount::where('email', 'new@rtl.test')->sole())->password->toBeNull()->on_server->toBeTrue()->quota_mb->toBeNull()
        ->and(EmailAccount::where('email', 'rtluser')->exists())->toBeFalse();
});

it('suspends, changes quota and deletes on the server', function () {
    connectCpanel();
    Http::fake([CPANEL.'*' => Http::response(cpanelOk())]);
    $account = mailbox();

    $this->actingAs($this->admin)->patch(route('admin.email.accounts.status', $account), ['status' => 'suspended'])->assertSessionHasNoErrors();
    $this->put(route('admin.email.accounts.update', $account), ['display_name' => 'Nadia', 'quota_mb' => 500, 'users' => []])->assertSessionHasNoErrors();
    $this->put(route('admin.email.accounts.password', $account), ['generate' => true])->assertSessionHasNoErrors();
    $this->delete(route('admin.email.accounts.destroy', $account), ['from_server' => true])->assertSessionHasNoErrors();

    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), 'Email/suspend_login') && $r['email'] === 'nadia@rtl.test');
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), 'Email/edit_pop_quota') && (int) $r['quota'] === 500);
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), 'Email/passwd_pop'));
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), 'Email/delete_pop') && $r['email'] === 'nadia');
    expect(EmailAccount::count())->toBe(0);
});

it('manages templates', function () {
    $this->actingAs($this->admin)->post(route('admin.email.templates.store'), [
        'name' => 'Payment reminder', 'category' => 'Payment', 'subject' => 'Payment due', 'body' => 'Dear {{ name }}', 'is_active' => true,
    ])->assertSessionHasNoErrors();
    $this->post(route('admin.email.templates.store'), ['name' => 'Payment reminder', 'subject' => 'x', 'body' => 'y', 'is_active' => true])
        ->assertSessionHasErrors('slug');

    $template = EmailTemplate::sole();
    $this->get(route('admin.email.templates.index'))->assertInertia(fn (Assert $p) => $p->where('templates.0.category', 'Payment'));
    $this->delete(route('admin.email.templates.destroy', $template))->assertSessionHasNoErrors();
    expect(EmailTemplate::count())->toBe(0);
});

it('lets a user save their signature', function () {
    $user = $this->userWithRole(Role::Accounts);

    $this->actingAs($user)->put(route('accounts.email.signature'), ['body' => 'Accounts team'])->assertSessionHasNoErrors();
    expect(User::find($user->id)->emailSignature->body)->toBe('Accounts team');

    $this->put(route('accounts.email.signature'), ['body' => ''])->assertSessionHasNoErrors();
    expect(User::find($user->id)->emailSignature)->toBeNull();
});
