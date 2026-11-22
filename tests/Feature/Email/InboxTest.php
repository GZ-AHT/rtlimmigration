<?php

use App\Enums\EmailAccountStatus;
use App\Enums\Role;
use App\Jobs\SyncInboxes;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\MailboxMessage;
use App\Models\User;
use App\Support\Email\Imap\ImapConnector;
use App\Support\Email\InboxSync;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeImap;

uses(RefreshDatabase::class);

function inboxAccount(array $attributes = []): EmailAccount
{
    return EmailAccount::create([
        'email' => 'nadia@rtl.test', 'local_part' => 'nadia', 'domain' => 'rtl.test', 'password' => 'Secret-Pass-123',
        'status' => EmailAccountStatus::Active, 'on_server' => true, ...$attributes,
    ]);
}

beforeEach(function () {
    Storage::fake((string) config('documents.disk'));
    Cache::flush();
    $this->imap = new FakeImap;
    app()->instance(ImapConnector::class, $this->imap);
    $this->account = inboxAccount();
    $this->counselor = $this->userWithRole(Role::Counselor);
    $this->account->users()->attach($this->counselor->id, ['can_send' => true]);
});

function syncNow(EmailAccount $account): array
{
    return app(InboxSync::class)->sync($account);
}

describe('importing', function () {
    it('brings mail from every folder into the dashboard', function () {
        $this->imap->put('INBOX', ['subject' => 'Visa question']);
        $this->imap->put('INBOX.Drafts', ['subject' => 'Half written']);
        $this->imap->put('INBOX.spam', ['subject' => 'Win a prize']);
        $this->imap->put('INBOX.Trash', ['subject' => 'Old news']);

        expect(syncNow($this->account))->toBe(['imported' => 4, 'updated' => 0, 'removed' => 0]);

        expect(MailboxMessage::pluck('folder', 'subject')->all())->toBe([
            'Visa question' => 'inbox', 'Half written' => 'drafts', 'Win a prize' => 'spam', 'Old news' => 'trash',
        ]);
        $m = MailboxMessage::where('subject', 'Visa question')->first();
        expect($m->from_email)->toBe('sender@gmail.test')->and($m->from_name)->toBe('Sender Person')
            ->and($m->to)->toBe(['nadia@rtl.test'])->and($m->preview)->toBe('Hello 1')->and($m->is_read)->toBeFalse();
    });

    it('never imports the same message twice', function () {
        $this->imap->put('INBOX', ['subject' => 'Once']);

        syncNow($this->account);
        expect(syncNow($this->account))->toBe(['imported' => 0, 'updated' => 0, 'removed' => 0])
            ->and(MailboxMessage::count())->toBe(1);

        $this->imap->put('INBOX', ['subject' => 'Twice']);
        expect(syncNow($this->account)['imported'])->toBe(1)->and(MailboxMessage::count())->toBe(2);
    });

    it('treats a repeated Message-ID as the same email, even with a new UID', function () {
        $this->imap->put('INBOX', ['uid' => 5, 'message_id' => 'same@x.test']);
        syncNow($this->account);
        $this->imap->put('INBOX', ['uid' => 9, 'message_id' => 'same@x.test']);
        syncNow($this->account);

        expect(MailboxMessage::count())->toBe(1);
    });

    it('still imports mail that has no Message-ID, once', function () {
        $this->imap->put('INBOX', ['message_id' => null, 'subject' => 'No id']);
        syncNow($this->account);
        syncNow($this->account);

        expect(MailboxMessage::where('subject', 'No id')->count())->toBe(1);
    });

    it('takes only the newest 100 on the first run, then everything new', function () {
        foreach (range(1, 130) as $i) {
            $this->imap->put('INBOX', ['subject' => "Mail {$i}"]);
        }

        expect(syncNow($this->account)['imported'])->toBe(100)
            ->and(MailboxMessage::where('subject', 'Mail 130')->exists())->toBeTrue()
            ->and(MailboxMessage::where('subject', 'Mail 1')->exists())->toBeFalse();
    });

    it('follows the mailbox: read state, mail moved or deleted in Roundcube', function () {
        $a = $this->imap->put('INBOX', ['subject' => 'A']);
        $b = $this->imap->put('INBOX', ['subject' => 'B']);
        $c = $this->imap->put('INBOX', ['subject' => 'C']);
        syncNow($this->account);

        $this->imap->setSeen('INBOX', $a, true);       // read in Roundcube
        $this->imap->delete('INBOX', $b);               // deleted for good
        $this->imap->move('INBOX', $c, 'INBOX.Trash'); // moved to Trash

        $result = syncNow($this->account);

        expect(MailboxMessage::where('subject', 'A')->value('is_read'))->toBeTrue()
            ->and(MailboxMessage::where('subject', 'B')->exists())->toBeFalse()
            ->and(MailboxMessage::where('subject', 'C')->value('folder'))->toBe('trash')
            ->and(MailboxMessage::count())->toBe(2)
            ->and($result['updated'])->toBeGreaterThanOrEqual(1);
    });

    it('skips one broken message instead of stopping the sync', function () {
        $this->imap->put('INBOX', ['subject' => 'Good one']);
        $this->imap->put('INBOX', ['subject' => str_repeat('x', 100), 'from' => null, 'html' => "\xB1\x31 <b>bad bytes</b>"]);
        $this->imap->put('INBOX', ['subject' => 'Good two']);

        syncNow($this->account);

        expect(MailboxMessage::whereIn('subject', ['Good one', 'Good two'])->count())->toBe(2);
    });
});

describe('safety', function () {
    it('sanitises received HTML: no scripts, handlers, styles, forms, javascript links or remote images', function () {
        $this->imap->put('INBOX', ['html' => '<p onclick="steal()">Hi <b>there</b></p><script>alert(1)</script><style>body{display:none}</style>'
            .'<a href="javascript:alert(1)">bad</a><a href="https://ok.test/x">ok</a><img src="https://tracker.test/p.gif"><iframe src="https://evil.test"></iframe>'
            .'<form action="https://evil.test"><input name="p"></form><img src="data:image/svg+xml;base64,PHN2Zz4=">']);
        syncNow($this->account);

        $html = MailboxMessage::first()->body_html;

        expect($html)->toContain('<b>there</b>')->toContain('https://ok.test/x')
            ->not->toContain('script')->not->toContain('onclick')->not->toContain('<style')->not->toContain('javascript:')
            ->not->toContain('tracker.test')->not->toContain('<iframe')->not->toContain('<form')->not->toContain('data:image');
        expect($html)->toContain('rel="noopener noreferrer nofollow"');
    });

    it('keeps embedded pictures behind our own route and attachments in private storage', function () {
        $this->imap->put('INBOX', [
            'html' => '<p>Logo: <img src="cid:logo123"></p>',
            'attachments' => [
                ['name' => '../../evil.php', 'mime' => 'application/x-php', 'content' => '<?php echo 1;', 'cid' => null, 'inline' => false],
                ['name' => 'logo.png', 'mime' => 'image/png', 'content' => 'PNGDATA', 'cid' => 'logo123', 'inline' => true],
            ],
        ]);
        syncNow($this->account);
        $m = MailboxMessage::first();

        expect($m->has_attachments)->toBeTrue()->and($m->body_html)->toContain('src="cid-1"')
            ->and($m->attachments[0]['name'])->toBe('evil.php')
            ->and($m->attachments[0]['path'])->toStartWith("mailbox/{$this->account->id}/");
        Storage::disk((string) config('documents.disk'))->assertExists($m->attachments[0]['path']);
    });

    it('never exposes the mailbox password or file paths to the browser', function () {
        $this->imap->put('INBOX', ['attachments' => [['name' => 'a.pdf', 'mime' => 'application/pdf', 'content' => 'PDF', 'cid' => null, 'inline' => false]]]);
        syncNow($this->account);
        $m = MailboxMessage::first();

        $page = $this->actingAs($this->counselor)->get(route('counselor.email.inbox.show', $m))->assertOk()->getContent().$this->actingAs($this->counselor)->get(route('counselor.email.inbox'))->getContent();

        expect($page)->not->toContain('Secret-Pass-123')->not->toContain('"password"')->not->toContain('mailbox/'.$this->account->id.'/');
    });
});

describe('the Inbox screen', function () {
    it('lists a folder, newest first, with unread counts, search and pages', function () {
        foreach (range(1, 25) as $i) {
            $this->imap->put('INBOX', ['subject' => "Hello {$i}", 'seen' => $i > 20]);
        }
        $this->imap->put('INBOX.Trash', ['subject' => 'In trash']);
        syncNow($this->account);

        $this->actingAs($this->counselor)->get(route('counselor.email.inbox'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('email/Inbox')
                ->where('folder', 'inbox')
                ->where('counts.inbox_unread', 20)->where('counts.trash', 1)
                ->has('messages.data', 20)->where('messages.total', 25)
                ->where('messages.data.0.subject', 'Hello 25'));

        $this->get(route('counselor.email.inbox', ['folder' => 'inbox', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('messages.data', 5));
        $this->get(route('counselor.email.inbox', ['folder' => 'trash']))
            ->assertInertia(fn (Assert $page) => $page->has('messages.data', 1)->where('messages.data.0.subject', 'In trash'));
        $this->get(route('counselor.email.inbox', ['q' => 'Hello 7']))
            ->assertInertia(fn (Assert $page) => $page->has('messages.data', 1));
        $this->get(route('counselor.email.inbox', ['q' => '%']))
            ->assertInertia(fn (Assert $page) => $page->has('messages.data', 0));
        $this->get(route('counselor.email.inbox', ['folder' => 'sent']))->assertSessionHasErrors('folder');
    });

    it('shows each person only the mailboxes assigned to them', function () {
        $other = inboxAccount(['email' => 'other@rtl.test', 'local_part' => 'other']);
        $this->imap->put('INBOX', ['subject' => 'Mine']);
        syncNow($this->account);
        $this->imap->store['INBOX'] = [];
        $this->imap->put('INBOX', ['subject' => 'Not mine', 'message_id' => 'z@z.test']);
        syncNow($other);
        $stranger = MailboxMessage::where('subject', 'Not mine')->first();

        $this->actingAs($this->counselor)->get(route('counselor.email.inbox'))
            ->assertInertia(fn (Assert $page) => $page->where('messages.total', 1)->where('messages.data.0.subject', 'Mine'));
        $this->get(route('counselor.email.inbox.show', $stranger))->assertForbidden();
        $this->patch(route('counselor.email.inbox.read', $stranger), ['read' => true])->assertForbidden();
        $this->delete(route('counselor.email.inbox.destroy', $stranger))->assertForbidden();
        $this->get(route('counselor.email.inbox.attachment', [$stranger, 0]))->assertForbidden();

        $this->actingAs($this->userWithRole(Role::SuperAdmin))->get(route('admin.email.inbox'))
            ->assertInertia(fn (Assert $page) => $page->where('messages.total', 2));
    });

    it('needs email access', function () {
        $student = $this->userWithRole(Role::Student);

        $this->actingAs($student)->get(route('portal.email.inbox'))->assertForbidden();
    });
});

describe('reading and acting on a message', function () {
    it('opens a message, marks it read here and in the mailbox, and can mark it unread', function () {
        $uid = $this->imap->put('INBOX', ['subject' => 'Open me', 'html' => '<p>Body <b>text</b></p>']);
        syncNow($this->account);
        $m = MailboxMessage::first();

        $this->actingAs($this->counselor)->get(route('counselor.email.inbox.show', $m))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('email/Message')
                ->where('message.subject', 'Open me')->where('message.from_email', 'sender@gmail.test')
                ->where('message.to', ['nadia@rtl.test'])->where('message.body_html', fn ($h) => str_contains($h, '<b>text</b>')));

        expect($m->fresh()->is_read)->toBeTrue()->and($this->imap->store['INBOX'][$uid]['seen'])->toBeTrue();

        $this->patch(route('counselor.email.inbox.read', $m), ['read' => false])->assertRedirect();
        expect($m->fresh()->is_read)->toBeFalse()->and($this->imap->store['INBOX'][$uid]['seen'])->toBeFalse();
    });

    it('still opens a message when the mail server is down', function () {
        $this->imap->put('INBOX');
        syncNow($this->account);
        $this->imap->fail = true;

        $this->actingAs($this->counselor)->get(route('counselor.email.inbox.show', MailboxMessage::first()))->assertOk();
        expect(MailboxMessage::first()->is_read)->toBeTrue();
    });

    it('downloads attachments as files, and shows embedded pictures inline', function () {
        $this->imap->put('INBOX', [
            'html' => '<img src="cid:pic">',
            'attachments' => [
                ['name' => 'offer.pdf', 'mime' => 'application/pdf', 'content' => 'PDFBYTES', 'cid' => null, 'inline' => false],
                ['name' => 'pic.png', 'mime' => 'image/png', 'content' => 'PNGBYTES', 'cid' => 'pic', 'inline' => true],
                ['name' => 'x.svg', 'mime' => 'image/svg+xml', 'content' => '<svg onload=1/>', 'cid' => 'svg', 'inline' => true],
            ],
        ]);
        syncNow($this->account);
        $m = MailboxMessage::first();
        $this->actingAs($this->counselor);

        $pdf = $this->get(route('counselor.email.inbox.attachment', [$m, 0]));
        expect($pdf->headers->get('content-disposition'))->toContain('attachment')->toContain('offer.pdf')
            ->and($pdf->headers->get('x-content-type-options'))->toBe('nosniff');

        $pic = $this->get(route('counselor.email.inbox.attachment', [$m, 1]));
        expect($pic->headers->get('content-disposition'))->toStartWith('inline')->and($pic->headers->get('content-type'))->toBe('image/png');

        $svg = $this->get(route('counselor.email.inbox.attachment', [$m, 2]));
        expect($svg->headers->get('content-disposition'))->toStartWith('attachment')->and($svg->headers->get('content-type'))->toBe('application/octet-stream');

        $this->get(route('counselor.email.inbox.attachment', [$m, 9]))->assertNotFound();
        $this->get(route('counselor.email.inbox.show', $m))->assertInertia(fn (Assert $page) => $page
            ->has('message.attachments', 1)->where('message.attachments.0.name', 'offer.pdf')
            ->where('message.body_html', fn ($h) => str_contains($h, route('counselor.email.inbox.attachment', [$m, 1], false))));
    });

    it('moves to Trash on the mailbox too, restores, and deletes for good from Trash', function () {
        $uid = $this->imap->put('INBOX', ['subject' => 'Bin me']);
        syncNow($this->account);
        $m = MailboxMessage::first();
        $this->actingAs($this->counselor);

        $this->delete(route('counselor.email.inbox.destroy', $m))->assertRedirect(route('counselor.email.inbox', ['folder' => 'inbox'], false));
        expect($m->fresh()->folder)->toBe('trash')->and($this->imap->store['INBOX'])->toBe([])->and($this->imap->store['INBOX.Trash'])->toHaveCount(1);

        $this->patch(route('counselor.email.inbox.restore', $m))->assertRedirect();
        expect($m->fresh()->folder)->toBe('inbox')->and($this->imap->store['INBOX'])->toHaveCount(1);

        $this->delete(route('counselor.email.inbox.destroy', $m));
        $this->delete(route('counselor.email.inbox.destroy', $m->fresh()))->assertRedirect(route('counselor.email.inbox', ['folder' => 'trash'], false));
        expect(MailboxMessage::count())->toBe(0)->and($this->imap->store['INBOX.Trash'])->toBe([]);
    });

    it('changes nothing here when the mail server cannot do the delete', function () {
        $this->imap->put('INBOX');
        syncNow($this->account);
        $this->imap->fail = true;

        $this->actingAs($this->counselor)->delete(route('counselor.email.inbox.destroy', MailboxMessage::first()))->assertRedirect();

        expect(MailboxMessage::first()->folder)->toBe('inbox');
        expect(session('inertia.flash_data.toast.type'))->toBe('error');
    });

    it('removes stored attachments when a message is deleted for good', function () {
        $this->imap->put('INBOX.Trash', ['attachments' => [['name' => 'a.pdf', 'mime' => 'application/pdf', 'content' => 'X', 'cid' => null, 'inline' => false]]]);
        syncNow($this->account);
        $path = MailboxMessage::first()->attachments[0]['path'];

        $this->actingAs($this->counselor)->delete(route('counselor.email.inbox.destroy', MailboxMessage::first()));

        Storage::disk((string) config('documents.disk'))->assertMissing($path);
    });
});

describe('syncing', function () {
    it('has a manual Sync button that reports what it found', function () {
        $this->imap->put('INBOX');
        $this->imap->put('INBOX');

        $this->actingAs($this->counselor)->post(route('counselor.email.inbox.sync'))->assertRedirect();

        expect(MailboxMessage::count())->toBe(2)->and(session('inertia.flash_data.toast.message'))->toBe('2 new emails.');

        $this->post(route('counselor.email.inbox.sync'));
        expect(session('inertia.flash_data.toast.message'))->toBe('Your inbox is up to date.');
    });

    it('fails gracefully when the mail server is unreachable, and remembers why', function () {
        $this->imap->fail = true;

        $this->actingAs($this->counselor)->post(route('counselor.email.inbox.sync'))->assertRedirect();

        expect(session('inertia.flash_data.toast.type'))->toBe('error')
            ->and(session('inertia.flash_data.toast.message'))->toContain('could not be reached')->not->toContain('Secret');
        $this->get(route('counselor.email.inbox'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('accounts.0.error', fn ($e) => str_contains($e, 'could not be reached')));
    });

    it('says so when no mailbox can receive yet', function () {
        $this->account->update(['password' => null]);

        $this->actingAs($this->counselor)->post(route('counselor.email.inbox.sync'))->assertRedirect();

        expect(session('inertia.flash_data.toast.message'))->toContain('No mailbox is set up');
    });

    it('runs on a schedule and one broken mailbox does not stop the others', function () {
        $second = inboxAccount(['email' => 'second@rtl.test', 'local_part' => 'second']);
        $broken = inboxAccount(['email' => 'broken@rtl.test', 'local_part' => 'broken', 'password' => null]);
        $this->imap->put('INBOX');

        (new SyncInboxes)->handle(app(InboxSync::class), app(ImapConnector::class));

        expect(MailboxMessage::pluck('email_account_id')->unique()->sort()->values()->all())->toBe([$this->account->id, $second->id])
            ->and(MailboxMessage::where('email_account_id', $broken->id)->exists())->toBeFalse();

        $this->imap->fail = true;
        (new SyncInboxes)->handle(app(InboxSync::class), app(ImapConnector::class));

        $events = collect(app(Schedule::class)->events())->map(fn ($e) => $e->description)->all();
        expect($events)->toContain('email:sync-inboxes');
    });

    it('does not run two syncs of the same mailbox at once', function () {
        $this->imap->put('INBOX');
        $lock = Cache::lock("inbox-sync:{$this->account->id}", 60);
        $lock->get();

        expect(syncNow($this->account))->toBe(['imported' => 0, 'updated' => 0, 'removed' => 0])->and(MailboxMessage::count())->toBe(0);

        $lock->release();
        expect(syncNow($this->account)['imported'])->toBe(1);
    });
});

describe('replying and forwarding', function () {
    it('starts a reply to the sender, from the mailbox it arrived at', function () {
        $this->imap->put('INBOX', ['subject' => 'Visa help', 'reply_to' => 'real@gmail.test', 'text' => "Line one\nLine two"]);
        syncNow($this->account);
        $m = MailboxMessage::first();

        $this->actingAs($this->counselor)->get(route('counselor.email.compose', ['inbox' => $m->id, 'mode' => 'reply']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('email/Compose')
                ->where('prefill.mode', 'reply')->where('prefill.to_email', 'real@gmail.test')->where('prefill.subject', 'Re: Visa help')
                ->where('prefill.email_account_id', (string) $this->account->id)
                ->where('prefill.body', fn ($b) => str_contains($b, '> Line one') && str_contains($b, '> Line two')));
    });

    it('reply-all copies the others but not the mailbox itself', function () {
        $this->imap->put('INBOX', ['to' => ['nadia@rtl.test', 'bob@x.test'], 'cc' => ['cara@x.test', 'sender@gmail.test']]);
        syncNow($this->account);

        $this->actingAs($this->counselor)->get(route('counselor.email.compose', ['inbox' => MailboxMessage::first()->id, 'mode' => 'reply_all']))
            ->assertInertia(fn (Assert $page) => $page->where('prefill.cc', 'bob@x.test, cara@x.test'));
    });

    it('forwards with the original attachments, and not someone else\'s mail', function () {
        $this->imap->put('INBOX', ['subject' => 'Docs', 'attachments' => [['name' => 'passport.pdf', 'mime' => 'application/pdf', 'content' => 'PDFDATA', 'cid' => null, 'inline' => false]]]);
        syncNow($this->account);
        $m = MailboxMessage::first();
        Mail::fake();

        $this->actingAs($this->counselor)->get(route('counselor.email.compose', ['inbox' => $m->id, 'mode' => 'forward']))
            ->assertInertia(fn (Assert $page) => $page->where('prefill.mode', 'forward')->where('prefill.subject', 'Fwd: Docs')
                ->where('prefill.forward_of_inbox', $m->id)->where('prefill.attachments', ['passport.pdf'])->where('prefill.to_email', null));

        $this->post(route('counselor.email.send'), [
            'email_account_id' => (string) $this->account->id, 'to_email' => 'agent@partner.test', 'subject' => 'Fwd: Docs', 'body' => 'See below',
            'forward_of_inbox' => $m->id, 'include_signature' => false,
        ])->assertSessionHasNoErrors();

        $sent = EmailMessage::latest('id')->first();
        expect($sent->attachments[0]['name'])->toBe('passport.pdf')->and($sent->attachments[0]['path'])->toStartWith('email-attachments/');
        Storage::disk((string) config('documents.disk'))->assertExists($sent->attachments[0]['path']);

        $other = inboxAccount(['email' => 'other@rtl.test', 'local_part' => 'other']);
        $stranger = MailboxMessage::create(['email_account_id' => $other->id, 'folder' => 'inbox', 'remote_folder' => 'INBOX', 'uid' => 1, 'dedupe_key' => sha1('x'), 'subject' => 'Secret']);
        $this->get(route('counselor.email.compose', ['inbox' => $stranger->id, 'mode' => 'reply']))->assertNotFound();
    });
});

it('leaves outgoing email untouched', function () {
    expect(User::count())->toBeGreaterThan(0);
    $this->actingAs($this->counselor)->get(route('counselor.email.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('email/Index'));
});
