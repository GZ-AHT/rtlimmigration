<?php

use App\Enums\EmailAccountStatus;
use App\Enums\EmailMessageStatus;
use App\Enums\Role;
use App\Jobs\SyncMailboxes;
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
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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

/** An email already sent by $user, as if composed in the dashboard. */
function sentBy(User $user, array $attributes = []): EmailMessage
{
    return EmailMessage::create([
        'uuid' => (string) Str::uuid(), 'sent_by' => $user->id, 'from_email' => 'info@rtl.test', 'from_name' => 'RTL',
        'to_email' => 'rafi@example.test', 'to_name' => 'Rafi Ahmed', 'subject' => 'Your offer', 'body' => '<p>Hello Rafi,</p><p>Your offer is attached.</p>',
        'status' => EmailMessageStatus::Sent, 'sent_at' => now(), ...$attributes,
    ]);
}

describe('CC and BCC', function () {
    it('delivers to cc and bcc through the mailer without showing bcc in the message', function () {
        $this->actingAs($this->admin)->post(route('admin.email.send'), [
            'to_email' => 'rafi@example.test', 'cc' => 'Boss@example.test; mum@example.test', 'bcc' => 'audit@example.test',
            'subject' => 'Offer', 'body' => 'Hello.',
        ])->assertSessionHasNoErrors();

        $message = EmailMessage::sole();
        expect($message->status)->toBe(EmailMessageStatus::Sent)
            ->and($message->cc)->toBe(['Boss@example.test', 'mum@example.test'])
            ->and($message->bcc)->toBe(['audit@example.test']);

        // What actually went to the mail transport (the "array" mailer records it).
        $sent = Mail::mailer()->getSymfonyTransport()->messages()->last();
        $envelope = collect($sent->getEnvelope()->getRecipients())->map(fn ($a) => strtolower($a->getAddress()))->all();
        expect($envelope)->toEqualCanonicalizing(['rafi@example.test', 'boss@example.test', 'mum@example.test', 'audit@example.test'])
            ->and($sent->toString())->toContain('Cc:')->not->toContain('audit@example.test');
    });

    it('sends cc and bcc through a mailbox\'s own SMTP mailer', function () {
        Mail::fake();
        $account = mailbox();

        $this->actingAs($this->admin)->post(route('admin.email.send'), [
            'email_account_id' => $account->id, 'to_email' => 'rafi@example.test', 'cc' => 'boss@example.test', 'bcc' => 'audit@example.test',
            'subject' => 'Offer', 'body' => 'Hello.',
        ])->assertSessionHasNoErrors();

        Mail::assertSent(OutgoingEmail::class, fn (OutgoingEmail $m) => $m->hasTo('rafi@example.test') && $m->hasCc('boss@example.test') && $m->hasBcc('audit@example.test'));
    });

    it('refuses bad addresses and too many, and drops repeats', function () {
        $post = fn (array $extra) => $this->actingAs($this->admin)->post(route('admin.email.send'), ['to_email' => 'rafi@example.test', 'subject' => 'S', 'body' => 'B', ...$extra]);

        $post(['cc' => 'fine@example.test, not-an-address'])->assertSessionHasErrors('cc');
        $post(['bcc' => 'x@y.test, "evil" <inject@example.test>'])->assertSessionHasErrors('bcc');
        $post(['cc' => implode(',', array_map(fn ($i) => "p{$i}@example.test", range(1, 11)))])->assertSessionHasErrors('cc');
        expect(EmailMessage::count())->toBe(0);

        $post(['cc' => 'RAFI@example.test, a@example.test, A@example.test', 'bcc' => 'a@example.test, b@example.test'])->assertSessionHasNoErrors();
        expect(EmailMessage::sole())->cc->toBe(['a@example.test'])->bcc->toBe(['b@example.test']);
    });

    it('leaves emails without cc or bcc exactly as before', function () {
        $this->actingAs($this->admin)->post(route('admin.email.send'), ['to_email' => 'rafi@example.test', 'subject' => 'S', 'body' => 'B'])->assertSessionHasNoErrors();

        expect(EmailMessage::sole())->cc->toBeNull()->bcc->toBeNull();
        $sent = Mail::mailer()->getSymfonyTransport()->messages()->last();
        expect(collect($sent->getEnvelope()->getRecipients())->map(fn ($a) => $a->getAddress())->all())->toBe(['rafi@example.test']);
    });
});

describe('reply and forward', function () {
    it('prefills a reply with the recipient, a Re: subject and the quoted original', function () {
        $original = sentBy($this->admin, ['cc' => ['boss@example.test']]);

        $this->actingAs($this->admin)->get(route('admin.email.compose', ['reply' => $original->id]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('prefill.mode', 'reply')->where('prefill.to_email', 'rafi@example.test')
                ->where('prefill.subject', 'Re: Your offer')->where('prefill.cc', 'boss@example.test')->where('prefill.forward_of', null)
                ->where('prefill.body', fn (string $b) => str_contains($b, 'wrote:') && str_contains($b, '> Your offer is attached.')));
    });

    it('does not stack Re: or Fwd: prefixes', function () {
        $original = sentBy($this->admin, ['subject' => 'Re: Your offer']);

        $this->actingAs($this->admin)->get(route('admin.email.compose', ['reply' => $original->id]))->assertInertia(fn (Assert $p) => $p->where('prefill.subject', 'Re: Your offer'));
        $this->actingAs($this->admin)->get(route('admin.email.compose', ['forward' => $original->id]))->assertInertia(fn (Assert $p) => $p->where('prefill.subject', 'Re: Your offer'));
    });

    it('keeps the student link on a reply and sends it through the normal queue', function () {
        $student = Student::factory()->create();
        $original = sentBy($this->admin, ['recipient_type' => $student->getMorphClass(), 'recipient_id' => $student->id, 'to_email' => $student->user->email]);

        $this->actingAs($this->admin)->get(route('admin.email.compose', ['reply' => $original->id]))
            ->assertInertia(fn (Assert $p) => $p->where('recipient.type', 'student')->where('recipient.id', $student->id));

        $this->actingAs($this->admin)->post(route('admin.email.send'), [
            'recipient_type' => 'student', 'recipient_id' => $student->id, 'subject' => 'Re: Your offer', 'body' => 'Thanks!',
        ])->assertSessionHasNoErrors();

        expect(EmailMessage::query()->latest('id')->first())->status->toBe(EmailMessageStatus::Sent)->recipient_id->toBe($student->id);
    });

    it('forwards with a blank recipient and copies the attachments to the new email', function () {
        Storage::fake(config('documents.disk'));
        $disk = Storage::disk(config('documents.disk'));
        $disk->put('email-attachments/original/offer.pdf', 'PDF-BYTES');
        $original = sentBy($this->admin, ['attachments' => [['name' => 'offer.pdf', 'path' => 'email-attachments/original/offer.pdf', 'mime' => 'application/pdf', 'size' => 9]]]);

        $this->actingAs($this->admin)->get(route('admin.email.compose', ['forward' => $original->id]))
            ->assertInertia(fn (Assert $p) => $p->where('prefill.mode', 'forward')->where('prefill.to_email', null)->where('prefill.subject', 'Fwd: Your offer')
                ->where('prefill.forward_of', $original->id)->where('prefill.attachments', ['offer.pdf'])
                ->where('prefill.body', fn (string $b) => str_contains($b, 'Forwarded message') && str_contains($b, 'From: RTL <info@rtl.test>')));

        $this->actingAs($this->admin)->post(route('admin.email.send'), [
            'to_email' => 'new@example.test', 'subject' => 'Fwd: Your offer', 'body' => 'FYI', 'forward_of' => $original->id,
        ])->assertSessionHasNoErrors();

        $copy = EmailMessage::query()->latest('id')->first();
        expect($copy->id)->not->toBe($original->id)->and($copy->attachments[0]['path'])->not->toBe($original->attachments[0]['path']);
        $disk->assertExists($copy->attachments[0]['path']);
        expect($disk->get($copy->attachments[0]['path']))->toBe('PDF-BYTES');
        $disk->assertExists($original->attachments[0]['path']);
    });

    it('only starts from your own sent emails', function () {
        $other = $this->userWithRole(Role::Counselor);
        $theirs = sentBy($other);

        $this->actingAs($this->admin)->get(route('admin.email.compose', ['reply' => $theirs->id]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.email.compose', ['forward' => $theirs->id]))->assertNotFound();
        $this->actingAs($this->admin)->post(route('admin.email.send'), ['to_email' => 'x@example.test', 'subject' => 'S', 'body' => 'B', 'forward_of' => $theirs->id])->assertNotFound();
        expect(EmailMessage::count())->toBe(1);
    });
});

describe('deleting from the sent list', function () {
    it('hides it from the sent list, keeps the record, and restores it', function () {
        $message = sentBy($this->admin);
        $url = fn (?string $view = null) => route('admin.email.index', $view ? ['view' => $view] : []);

        $this->actingAs($this->admin)->delete(route('admin.email.messages.trash', $message))->assertSessionHasNoErrors();

        expect($message->fresh())->not->toBeNull()->trashed_at->not->toBeNull();
        $this->get($url())->assertInertia(fn (Assert $p) => $p->has('sent.data', 0)->where('trashCount', 1)->where('view', 'sent'));
        $this->get($url('trash'))->assertInertia(fn (Assert $p) => $p->has('sent.data', 1)->where('sent.data.0.id', $message->id)->where('view', 'trash'));
        // The delivery log keeps it.
        $this->get(route('admin.email.logs.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('messages.data.0.id', $message->id));
        expect(Activity::query()->where('description', 'Removed an email from the sent list')->count())->toBe(1);

        $this->patch(route('admin.email.messages.restore', $message))->assertSessionHasNoErrors();
        expect($message->fresh()->trashed_at)->toBeNull();
        $this->get($url())->assertInertia(fn (Assert $p) => $p->has('sent.data', 1)->where('trashCount', 0));
    });

    it('lets you delete only your own emails', function () {
        $counselor = $this->userWithRole(Role::Counselor);
        $mine = sentBy($counselor);
        $theirs = sentBy($this->admin);

        $this->actingAs($counselor)->delete(route('counselor.email.messages.trash', $theirs))->assertForbidden();
        $this->actingAs($counselor)->patch(route('counselor.email.messages.restore', $theirs))->assertForbidden();
        expect($theirs->fresh()->trashed_at)->toBeNull();

        // A counselor sees only their own list, never the admin's.
        $this->get(route('counselor.email.index'))->assertInertia(fn (Assert $p) => $p->has('sent.data', 1)->where('sent.data.0.id', $mine->id));
        $this->actingAs($counselor)->delete(route('counselor.email.messages.trash', $mine))->assertSessionHasNoErrors();
        expect($mine->fresh()->trashed_at)->not->toBeNull();
    });

    it('does not let a queued email be deleted before it is sent', function () {
        $queued = sentBy($this->admin, ['status' => EmailMessageStatus::Queued, 'sent_at' => null]);

        $this->actingAs($this->admin)->delete(route('admin.email.messages.trash', $queued));

        expect($queued->fresh()->trashed_at)->toBeNull();
    });

    it('keeps the student history and the file on disk when a sent email is deleted', function () {
        $student = Student::factory()->create();
        $message = sentBy($this->admin, ['recipient_type' => $student->getMorphClass(), 'recipient_id' => $student->id, 'subject' => 'Visa update']);

        $this->actingAs($this->admin)->delete(route('admin.email.messages.trash', $message));

        $this->get(route('admin.students.show', ['student' => $student, 'tab' => 'emails']))
            ->assertInertia(fn (Assert $p) => $p->where('emails.items.0.subject', 'Visa update'));
    });

    it('requires a signed-in user with email access', function () {
        $message = sentBy($this->admin);
        $student = $this->userWithRole(Role::Student);

        $this->delete('/admin/email/messages/'.$message->id)->assertRedirect();
        $this->actingAs($student)->delete('/admin/email/messages/'.$message->id)->assertForbidden();
        expect($message->fresh()->trashed_at)->toBeNull();
    });
});

describe('automatic mailbox sync', function () {
    it('is scheduled every thirty minutes', function () {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'email:sync-mailboxes');

        expect($event)->not->toBeNull()->and($event->expression)->toBe('*/30 * * * *');
    });

    it('imports and updates mailboxes from cPanel without creating duplicates', function () {
        connectCpanel();
        $known = mailbox(['email' => 'old@rtl.test', 'local_part' => 'old']);
        Http::fake([CPANEL.'Email/list_pops_with_disk' => Http::response(cpanelOk([
            ['email' => 'old@rtl.test', 'diskused' => '7', 'diskquota' => '512', 'suspended_login' => 0],
            ['email' => 'new@rtl.test', 'diskused' => '0', 'diskquota' => 'unlimited', 'suspended_login' => 0],
        ]))]);

        dispatch_sync(new SyncMailboxes);
        dispatch_sync(new SyncMailboxes);

        expect(EmailAccount::count())->toBe(2)->and($known->fresh())->disk_used_mb->toBe(7.0)->quota_mb->toBe(512)
            ->and($known->fresh()->password)->toBe('Secret-Pass-123'); // credentials untouched
        expect(Activity::query()->where('description', 'Mailboxes synced from the mail server')->count())->toBe(2);
    });

    it('does nothing when cPanel is not connected', function () {
        Http::fake();

        dispatch_sync(new SyncMailboxes);

        Http::assertNothingSent();
    });

    it('survives an unreachable server and tries again next time', function () {
        connectCpanel();
        mailbox();
        Http::fake([CPANEL.'*' => Http::response(['status' => 0, 'errors' => ['Server busy'], 'data' => null])]);

        dispatch_sync(new SyncMailboxes);

        expect(EmailAccount::sole()->on_server)->toBeTrue(); // nothing was marked missing by a failed call
    });

    it('keeps the manual Sync button working', function () {
        connectCpanel();
        Http::fake([CPANEL.'Email/list_pops_with_disk' => Http::response(cpanelOk([['email' => 'by-hand@rtl.test', 'diskused' => '0', 'diskquota' => 'unlimited', 'suspended_login' => 0]]))]);

        $this->actingAs($this->admin)->post(route('admin.email.accounts.sync'))->assertSessionHasNoErrors();

        expect(EmailAccount::where('email', 'by-hand@rtl.test')->exists())->toBeTrue();
    });
});
