<?php

namespace Tests\Support;

use App\Models\EmailAccount;
use App\Support\Email\EmailSettings;
use App\Support\Email\Imap\ImapConnector;
use App\Support\Email\Imap\ImapException;
use App\Support\Email\Imap\MailboxConnection;
use App\Support\Email\Imap\RemoteMessage;
use Carbon\CarbonImmutable;

/**
 * A tiny in-memory mail server for tests: folders of messages by UID, the
 * connection the app would open, and switches to make it fail.
 */
class FakeImap extends ImapConnector implements MailboxConnection
{
    /** @var array<string, array<int, array{msg: RemoteMessage, seen: bool}>> */
    public array $store = ['INBOX' => [], 'INBOX.Drafts' => [], 'INBOX.spam' => [], 'INBOX.Trash' => []];

    public bool $fail = false;

    public int $connects = 0;

    /** @var array{host: string, port: int, encryption: string}|null */
    public ?array $lastServer = null;

    public ?string $lastLogin = null;

    private int $next = 1;

    public function __construct()
    {
        parent::__construct(app(EmailSettings::class));
    }

    public function ready(EmailAccount $account): bool
    {
        return $account->isActive() && $account->on_server && filled($account->password);
    }

    public function connect(EmailAccount $account, ?array $server = null): MailboxConnection
    {
        $this->connects++;
        $this->lastServer = $server;
        $this->lastLogin = $account->loginName();

        if (blank($account->password)) {
            throw new ImapException('This mailbox has no saved password yet.');
        }

        if ($this->fail) {
            throw new ImapException('The mail server could not be reached. Check the IMAP server and port.');
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $o
     */
    public function put(string $folder, array $o = []): int
    {
        $uid = $o['uid'] ?? $this->next++;
        $this->next = max($this->next, $uid + 1);
        $this->store[$folder][$uid] = ['seen' => $o['seen'] ?? false, 'msg' => new RemoteMessage(
            uid: $uid,
            messageId: array_key_exists('message_id', $o) ? $o['message_id'] : "m{$uid}@gmail.test",
            fromEmail: $o['from'] ?? 'sender@gmail.test',
            fromName: $o['from_name'] ?? 'Sender Person',
            to: $o['to'] ?? ['nadia@rtl.test'],
            cc: $o['cc'] ?? [],
            replyTo: $o['reply_to'] ?? null,
            subject: $o['subject'] ?? "Subject {$uid}",
            date: CarbonImmutable::parse($o['date'] ?? '2026-11-20 10:00:00')->addMinutes($uid),
            text: $o['text'] ?? "Hello {$uid}",
            html: $o['html'] ?? null,
            attachments: $o['attachments'] ?? [],
            seen: $o['seen'] ?? false,
        )];

        return $uid;
    }

    public function folders(): array
    {
        return ['inbox' => 'INBOX', 'drafts' => 'INBOX.Drafts', 'spam' => 'INBOX.spam', 'trash' => 'INBOX.Trash'];
    }

    public function index(string $folder): array
    {
        return array_map(fn (array $r) => $r['seen'], $this->store[$folder] ?? []);
    }

    public function fetch(string $folder, array $uids): array
    {
        return array_values(array_map(fn (int $uid) => $this->store[$folder][$uid]['msg'], array_filter($uids, fn (int $uid) => isset($this->store[$folder][$uid]))));
    }

    public function setSeen(string $folder, int $uid, bool $seen): void
    {
        if (isset($this->store[$folder][$uid])) {
            $this->store[$folder][$uid]['seen'] = $seen;
        }
    }

    public function move(string $folder, int $uid, string $to): ?int
    {
        if (! isset($this->store[$folder][$uid])) {
            return null;
        }

        $row = $this->store[$folder][$uid];
        unset($this->store[$folder][$uid]);
        $new = $this->next++;
        $this->store[$to][$new] = ['seen' => $row['seen'], 'msg' => new RemoteMessage(
            $new, $row['msg']->messageId, $row['msg']->fromEmail, $row['msg']->fromName, $row['msg']->to, $row['msg']->cc, $row['msg']->replyTo,
            $row['msg']->subject, $row['msg']->date, $row['msg']->text, $row['msg']->html, $row['msg']->attachments, $row['seen'],
        )];

        return $new;
    }

    public function delete(string $folder, int $uid): bool
    {
        $had = isset($this->store[$folder][$uid]);
        unset($this->store[$folder][$uid]);

        return $had;
    }

    public function close(): void {}
}
