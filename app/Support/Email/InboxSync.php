<?php

namespace App\Support\Email;

use App\Models\EmailAccount;
use App\Models\MailboxMessage;
use App\Support\Email\Imap\ImapConnector;
use App\Support\Email\Imap\ImapException;
use App\Support\Email\Imap\MailboxConnection;
use App\Support\Email\Imap\RemoteMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Copies a cPanel mailbox into the dashboard over IMAP and keeps it in step:
 * new mail comes in, read/unread follows the mailbox, mail moved, deleted or
 * emptied from the trash in Roundcube moves or disappears here too.
 *
 * One row per message per mailbox (unique dedupe_key), so running it twice,
 * or from two places at once, never imports a message twice.
 */
class InboxSync
{
    /** Newest messages taken from a folder on the first run. */
    public const FIRST_RUN = 100;

    /** Most new messages imported per folder per run; the rest follow on the next run. */
    public const PER_RUN = 200;

    private const CHUNK = 10;

    private const MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

    public function __construct(private ImapConnector $connector, private EmailHtml $html) {}

    /**
     * @return array{imported: int, updated: int, removed: int}
     *
     * @throws ImapException
     */
    public function sync(EmailAccount $account): array
    {
        $lock = Cache::lock("inbox-sync:{$account->id}", 600);

        if (! $lock->get()) {
            return ['imported' => 0, 'updated' => 0, 'removed' => 0];
        }

        try {
            $connection = $this->connector->connect($account);

            try {
                $result = $this->run($account, $connection);
            } finally {
                $connection->close();
            }

            Cache::forever("inbox-synced:{$account->id}", now()->toIso8601String());
            Cache::forget("inbox-error:{$account->id}");

            return $result;
        } catch (ImapException $e) {
            Cache::forever("inbox-error:{$account->id}", $e->getMessage());

            throw $e;
        } finally {
            $lock->release();
        }
    }

    /** @return array{imported: int, updated: int, removed: int} */
    private function run(EmailAccount $account, MailboxConnection $connection): array
    {
        $totals = ['imported' => 0, 'updated' => 0, 'removed' => 0];
        $folders = $connection->folders();

        foreach (MailboxMessage::FOLDERS as $role) {
            if (! isset($folders[$role])) {
                continue;
            }

            $part = $this->syncFolder($account, $connection, $role, $folders[$role]);

            foreach ($part as $key => $n) {
                $totals[$key] += $n;
            }
        }

        return $totals;
    }

    /** @return array{imported: int, updated: int, removed: int} */
    private function syncFolder(EmailAccount $account, MailboxConnection $connection, string $role, string $remote): array
    {
        $result = ['imported' => 0, 'updated' => 0, 'removed' => 0];
        $server = $connection->index($remote); // uid => seen
        $local = MailboxMessage::query()->where('email_account_id', $account->id)->where('remote_folder', $remote)->get(['id', 'uid', 'is_read', 'dedupe_key', 'attachments']);
        $localByUid = $local->keyBy('uid');

        // Gone from the server folder (deleted, emptied or moved away): forget it here.
        foreach ($local as $row) {
            if (! array_key_exists($row->uid, $server)) {
                $this->forget($row);
                $result['removed']++;
            }
        }

        // Read / unread follows the mailbox.
        foreach ($server as $uid => $seen) {
            $row = $localByUid->get($uid);

            if ($row !== null && $row->is_read !== $seen) {
                MailboxMessage::whereKey($row->id)->update(['is_read' => $seen]);
                $result['updated']++;
            }
        }

        $new = array_values(array_diff(array_keys($server), $localByUid->keys()->all()));
        sort($new);
        $new = $local->isEmpty() ? array_slice($new, -self::FIRST_RUN) : array_slice($new, 0, self::PER_RUN);

        foreach (array_chunk($new, self::CHUNK) as $chunk) {
            foreach ($connection->fetch($remote, $chunk) as $message) {
                try {
                    $this->store($account, $role, $remote, $message) ? $result['imported']++ : $result['updated']++;
                } catch (Throwable $e) {
                    // One unreadable message must not stop the rest.
                    Log::warning("Inbox sync: skipped a message in {$account->email} {$remote}: ".$e->getMessage());
                }
            }
        }

        return $result;
    }

    /** @return bool true when a new row was created */
    private function store(EmailAccount $account, string $role, string $remote, RemoteMessage $m): bool
    {
        $key = $this->dedupeKey($account, $m);
        $existing = MailboxMessage::query()->where('email_account_id', $account->id)->where('dedupe_key', $key)->first();

        if ($existing !== null) {
            // Same message seen again (moved to another folder, or a repeat): follow where the server has it now.
            $existing->forceFill(['folder' => $role, 'remote_folder' => $remote, 'uid' => $m->uid, 'is_read' => $m->seen])->save();

            return false;
        }

        $files = $this->saveAttachments($account, $key, $m);
        $text = $this->utf8($m->text);
        $html = $m->html !== null ? $this->html->clean($this->utf8($m->html), array_map(fn (array $a) => $a['cid'], $files)) : null;

        MailboxMessage::create([
            'email_account_id' => $account->id,
            'folder' => $role,
            'remote_folder' => $remote,
            'uid' => $m->uid,
            'message_id' => $m->messageId !== null ? Str::limit($m->messageId, 250, '') : null,
            'dedupe_key' => $key,
            'from_email' => $m->fromEmail !== null ? Str::limit($m->fromEmail, 250, '') : null,
            'from_name' => $m->fromName !== null ? Str::limit($this->utf8($m->fromName), 250, '') : null,
            'to' => array_slice($m->to, 0, 50),
            'cc' => array_slice($m->cc, 0, 50),
            'reply_to' => $m->replyTo,
            'subject' => Str::limit($this->utf8($m->subject), 480, ''),
            'preview' => EmailHtml::toPreview($text, $html),
            'body_text' => $text !== null ? Str::limit($text, 500_000, '') : null,
            'body_html' => $html,
            'attachments' => array_map(fn (array $a) => array_diff_key($a, ['content' => 1]), $files) ?: null,
            'has_attachments' => collect($files)->contains(fn (array $a) => ! $a['inline']),
            'is_read' => $m->seen,
            'received_at' => $m->date ?? now(),
        ]);

        return true;
    }

    /** Message-ID where there is one; otherwise a fingerprint of the headers and start of the body. */
    private function dedupeKey(EmailAccount $account, RemoteMessage $m): string
    {
        $basis = $m->messageId !== null
            ? 'id:'.strtolower($m->messageId)
            : 'h:'.implode('|', [$m->fromEmail, $m->date?->toIso8601String(), $m->subject, mb_substr((string) ($m->text ?? $m->html), 0, 200)]);

        return sha1($account->id.'|'.$basis);
    }

    /**
     * @return list<array{name: string, path: string, mime: string, size: int, cid: string|null, inline: bool, content?: string}>
     */
    private function saveAttachments(EmailAccount $account, string $key, RemoteMessage $m): array
    {
        $disk = Storage::disk((string) config('documents.disk'));
        $saved = [];

        foreach ($m->attachments as $i => $a) {
            $size = strlen($a['content']);
            $name = $this->safeName($a['name']);
            $path = $size <= self::MAX_ATTACHMENT_BYTES ? "mailbox/{$account->id}/{$key}/{$i}-{$name}" : '';

            if ($path !== '') {
                $disk->put($path, $a['content']);
            }

            $saved[] = ['name' => $name, 'path' => $path, 'mime' => Str::limit($a['mime'], 100, ''), 'size' => $size, 'cid' => $a['cid'], 'inline' => $a['inline']];
        }

        return $saved;
    }

    private function forget(MailboxMessage $row): void
    {
        foreach ($row->attachments ?? [] as $a) {
            if ($a['path'] !== '') {
                Storage::disk((string) config('documents.disk'))->delete($a['path']);
            }
        }

        $row->delete();
    }

    /** Deletes a stored message and its files (used by the permanent delete too). */
    public function discard(MailboxMessage $message): void
    {
        $this->forget($message);
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $this->utf8($name) ?? ''));
        $name = trim((string) preg_replace('/[^\p{L}\p{N}\s._()\-]/u', '_', $name));

        return Str::limit($name !== '' ? $name : 'attachment', 120, '');
    }

    private function utf8(?string $value): ?string
    {
        return $value === null ? null : mb_scrub($value, 'UTF-8');
    }
}
