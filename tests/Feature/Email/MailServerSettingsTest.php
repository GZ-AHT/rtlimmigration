<?php

use App\Enums\EmailAccountStatus;
use App\Enums\Role;
use App\Models\EmailAccount;
use App\Support\Email\ConnectionTester;
use App\Support\Email\EmailSender;
use App\Support\Email\EmailSettings;
use App\Support\Email\Imap\ImapConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\Support\FakeImap;

uses(RefreshDatabase::class);

const SECRET = 'Secret-Pass-123';

function serverBox(array $attributes = []): EmailAccount
{
    return EmailAccount::create([
        'email' => 'nadia@rtlimmigration.com', 'local_part' => 'nadia', 'domain' => 'rtlimmigration.com', 'password' => SECRET,
        'status' => EmailAccountStatus::Active, 'on_server' => true, ...$attributes,
    ]);
}

/** @return array<string, mixed> */
function serverForm(array $over = []): array
{
    return ['email_account_id' => (EmailAccount::query()->where('email', 'nadia@rtlimmigration.com')->first() ?? serverBox())->id, 'host' => 'mail.rtlimmigration.com', 'port' => 993, 'encryption' => 'ssl', ...$over];
}

/** A stand-in SMTP session: records who signed in, and can refuse like a real server would. */
function fakeSmtp(bool $refuse = false): object
{
    $transport = new class('mail.rtlimmigration.com', 465, true) extends EsmtpTransport
    {
        public bool $refuse = false;

        public ?string $seenUser = null;

        public ?string $seenPassword = null;

        public ?string $seenHost = null;

        public ?int $seenPort = null;

        public bool $started = false;

        public function start(): void
        {
            $this->seenUser = $this->getUsername();
            $this->seenPassword = $this->getPassword();
            $this->started = true;

            if ($this->refuse) {
                throw new TransportException('Expected response code "235" but got code "535", with message "535 Authentication failed for '.$this->getUsername().' using password '.$this->getPassword().'".');
            }
        }

        public function stop(): void {}
    };
    $transport->refuse = $refuse;

    app()->instance(ConnectionTester::class, new class(app(ImapConnector::class), app(EmailSettings::class), $transport) extends ConnectionTester
    {
        public function __construct(ImapConnector $imap, EmailSettings $settings, private EsmtpTransport $fake)
        {
            parent::__construct($imap, $settings);
        }

        protected function transport(string $host, int $port, bool $ssl): EsmtpTransport
        {
            $this->fake->seenHost = $host;
            $this->fake->seenPort = $port;

            return $this->fake;
        }
    });

    return $transport;
}

beforeEach(function () {
    $this->imap = new FakeImap;
    app()->instance(ImapConnector::class, $this->imap);
    $this->admin = $this->userWithRole(Role::SuperAdmin);
});

describe('configurable server settings', function () {
    it('starts with the RTL mail server and standard secure ports, all editable', function () {
        $this->actingAs($this->admin)->get(route('admin.email.settings'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/email/Settings')
                ->where('settings.imap_host', 'mail.rtlimmigration.com')->where('settings.imap_port', 993)->where('settings.imap_encryption', 'ssl')
                ->where('settings.smtp_host', 'mail.rtlimmigration.com')->where('settings.smtp_port', 465)->where('settings.smtp_encryption', 'ssl'));

        expect(app(EmailSettings::class)->imap())->toBe(['host' => 'mail.rtlimmigration.com', 'port' => 993, 'encryption' => 'ssl'])
            ->and(app(EmailSettings::class)->smtp())->toBe(['host' => 'mail.rtlimmigration.com', 'port' => 465, 'encryption' => 'ssl']);
    });

    it('saves and uses a different host, port and IMAP security', function () {
        $payload = [
            'cpanel_host' => '', 'cpanel_port' => 2083, 'cpanel_username' => '', 'verify_tls' => true, 'domains' => [], 'default_quota_mb' => null, 'webmail_url' => '',
            'smtp_host' => 'smtp.other.test', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
            'imap_host' => 'imap.other.test', 'imap_port' => 143, 'imap_encryption' => 'tls', 'tracking_enabled' => true,
        ];

        $this->actingAs($this->admin)->put(route('admin.email.settings.update'), $payload)->assertSessionHasNoErrors();

        expect(app(EmailSettings::class)->imap())->toBe(['host' => 'imap.other.test', 'port' => 143, 'encryption' => 'starttls'])
            ->and(app(EmailSettings::class)->smtp())->toBe(['host' => 'smtp.other.test', 'port' => 587, 'encryption' => 'tls']);

        $this->put(route('admin.email.settings.update'), [...$payload, 'imap_encryption' => 'bogus'])->assertSessionHasErrors('imap_encryption');
        // Saving from an older form that has no IMAP security keeps working.
        unset($payload['imap_encryption']);
        $this->put(route('admin.email.settings.update'), $payload)->assertSessionHasNoErrors();
        expect(app(EmailSettings::class)->imap()['encryption'])->toBe('ssl');
    });

    it('keeps each mailbox\'s password encrypted and out of every page', function () {
        $box = serverBox();

        expect($box->getRawOriginal('password'))->not->toBe(SECRET)->and($box->getRawOriginal('password'))->not->toContain('Secret');

        $html = $this->actingAs($this->admin)->get(route('admin.email.settings'))->getContent().$this->get(route('admin.email.accounts.index'))->getContent();
        expect($html)->not->toContain(SECRET);
        $this->get(route('admin.email.settings'))->assertInertia(fn (Assert $page) => $page->where('mailboxes.0.has_password', true)->missing('mailboxes.0.password'));
    });
});

describe('a login name for each mailbox', function () {
    it('defaults to the address and can be changed by an administrator', function () {
        $box = serverBox();
        expect($box->loginName())->toBe('nadia@rtlimmigration.com');

        $this->actingAs($this->admin)->put(route('admin.email.accounts.username', $box), ['username' => 'nadia_login'])->assertSessionHasNoErrors();
        expect($box->fresh()->loginName())->toBe('nadia_login');
        $this->get(route('admin.email.accounts.index'))->assertInertia(fn (Assert $page) => $page->where('accounts.data.0.username', 'nadia_login')->where('accounts.data.0.login_name', 'nadia_login'));

        $this->put(route('admin.email.accounts.username', $box), ['username' => ''])->assertSessionHasNoErrors();
        expect($box->fresh()->username)->toBeNull()->and($box->fresh()->loginName())->toBe('nadia@rtlimmigration.com');

        $this->put(route('admin.email.accounts.username', $box), ['username' => 'nadia@rtlimmigration.com']);
        expect($box->fresh()->username)->toBeNull();
        $this->put(route('admin.email.accounts.username', $box), ['username' => 'has space'])->assertSessionHasErrors('username');
        expect(Activity::where('description', 'Changed a mailbox login name')->count())->toBe(3);
    });

    it('is only for administrators', function () {
        $box = serverBox();

        $this->actingAs($this->userWithRole(Role::Counselor))->put(route('admin.email.accounts.username', $box), ['username' => 'x'])->assertForbidden();
    });

    it('is the name used to sign in for sending and for receiving', function () {
        $box = serverBox(['username' => 'nadia_login']);

        $config = null;
        app(EmailSender::class)->mailerFor($box);
        $config = config('mail.mailers.mailbox-'.$box->id);
        expect($config['username'])->toBe('nadia_login');

        $this->actingAs($this->admin)->post(route('admin.email.settings.test-imap'), serverForm(['email_account_id' => $box->id]))->assertSessionHasNoErrors();
        expect($this->imap->lastLogin)->toBe('nadia_login');
    });
});

describe('Test IMAP connection', function () {
    it('tries the settings in the form (unsaved) and says what it found', function () {
        $this->actingAs($this->admin)->post(route('admin.email.settings.test-imap'), serverForm(['host' => 'imap.try-this.test', 'port' => 143, 'encryption' => 'tls']))
            ->assertSessionHasNoErrors();

        expect($this->imap->lastServer)->toBe(['host' => 'imap.try-this.test', 'port' => 143, 'encryption' => 'starttls']);
        $toast = session('inertia.flash_data.toast');
        expect($toast['type'])->toBe('success')->and($toast['message'])->toContain('imap.try-this.test')->toContain('nadia@rtlimmigration.com')->toContain('Inbox, Drafts, Spam, Trash');
        expect(app(EmailSettings::class)->imap()['host'])->toBe('mail.rtlimmigration.com'); // nothing was saved
    });

    it('reports a failure plainly, without the password', function () {
        $this->imap->fail = true;

        $this->actingAs($this->admin)->post(route('admin.email.settings.test-imap'), serverForm())->assertSessionHasErrors('imap_test');

        expect(session('errors')->getBag('default')->first('imap_test'))->toContain('could not be reached')->not->toContain(SECRET);
    });

    it('needs a saved password, a real server name and an administrator', function () {
        $noPass = serverBox(['password' => null, 'email' => 'bare@rtlimmigration.com', 'local_part' => 'bare']);

        $this->actingAs($this->admin)->post(route('admin.email.settings.test-imap'), serverForm(['email_account_id' => $noPass->id]))->assertSessionHasErrors('imap_test');
        $this->post(route('admin.email.settings.test-imap'), serverForm(['host' => 'https://mail.x.test']))->assertSessionHasErrors('host');
        $this->post(route('admin.email.settings.test-imap'), serverForm(['host' => '127.0.0.1']))->assertSessionHasErrors('host');
        $this->post(route('admin.email.settings.test-imap'), serverForm(['host' => '10.0.0.5']))->assertSessionHasErrors('host');
        $this->post(route('admin.email.settings.test-imap'), serverForm(['port' => 0]))->assertSessionHasErrors('port');
        $this->post(route('admin.email.settings.test-imap'), serverForm(['encryption' => 'none']))->assertSessionHasErrors('encryption');
        $this->post(route('admin.email.settings.test-imap'), serverForm(['email_account_id' => 9999]))->assertSessionHasErrors('email_account_id');

        $this->actingAs($this->userWithRole(Role::Counselor))->post(route('admin.email.settings.test-imap'), serverForm())->assertForbidden();
    });
});

describe('Test SMTP connection', function () {
    it('signs in with the mailbox login and stored password, and sends nothing', function () {
        Mail::fake();
        $smtp = fakeSmtp();
        $box = serverBox(['username' => 'nadia_login']);

        $this->actingAs($this->admin)->post(route('admin.email.settings.test-smtp'), serverForm(['email_account_id' => $box->id, 'host' => 'smtp.try-this.test', 'port' => 465, 'encryption' => 'ssl']))
            ->assertSessionHasNoErrors();

        expect($smtp->seenUser)->toBe('nadia_login')->and($smtp->seenPassword)->toBe(SECRET)->and($smtp->seenHost)->toBe('smtp.try-this.test')->and($smtp->seenPort)->toBe(465);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        expect(session('inertia.flash_data.toast.message'))->toContain('smtp.try-this.test')->not->toContain(SECRET);
    });

    it('shows the server\'s refusal with the login and password blanked out', function () {
        fakeSmtp(refuse: true);

        $this->actingAs($this->admin)->post(route('admin.email.settings.test-smtp'), serverForm())->assertSessionHasErrors('smtp_test');

        $message = session('errors')->getBag('default')->first('smtp_test');
        expect($message)->toContain('535')->toContain('Authentication failed')->not->toContain(SECRET)->not->toContain('nadia@rtlimmigration.com')->toContain('•••');
    });

    it('needs a saved password and an administrator', function () {
        fakeSmtp();
        $noPass = serverBox(['password' => null, 'email' => 'bare@rtlimmigration.com', 'local_part' => 'bare']);

        $this->actingAs($this->admin)->post(route('admin.email.settings.test-smtp'), serverForm(['email_account_id' => $noPass->id]))->assertSessionHasErrors('smtp_test');
        $this->actingAs($this->userWithRole(Role::Counselor))->post(route('admin.email.settings.test-smtp'), serverForm())->assertForbidden();
    });

    it('is logged without ever recording a password', function () {
        fakeSmtp();
        $this->actingAs($this->admin)->post(route('admin.email.settings.test-smtp'), serverForm())->assertSessionHasNoErrors();
        $this->imap->fail = false;
        $this->post(route('admin.email.settings.test-imap'), serverForm());

        $log = Activity::whereIn('description', ['Tested the SMTP connection', 'Tested the IMAP connection'])->get();
        expect($log)->toHaveCount(2)->and(json_encode($log->toArray()))->not->toContain('Secret');
    });
});
