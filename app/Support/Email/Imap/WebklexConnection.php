<?php

namespace App\Support\Email\Imap;

use Carbon\CarbonImmutable;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

/** The real IMAP connection (pure PHP; no imap extension needed). */
class WebklexConnection implements MailboxConnection
{
    private Client $client;

    /** @param array<string, mixed> $account */
    public function __construct(array $account)
    {
        try {
            $this->client = (new ClientManager)->make($account);
            $this->client->connect();
        } catch (Throwable $e) {
            // Never pass the server's message on blindly: it can echo the username.
            throw new ImapException($this->friendly($e), previous: $e);
        }
    }

    public function folders(): array
    {
        $found = [];

        try {
            foreach ($this->client->getFolders(false) as $folder) {
                $role = $this->role($folder);

                if ($role !== null) {
                    $found[$role] ??= $folder->path;
                }
            }
        } catch (Throwable $e) {
            throw new ImapException($this->friendly($e), previous: $e);
        }

        return $found;
    }

    public function index(string $folder): array
    {
        try {
            $messages = $this->folder($folder)->messages()->all()->setFetchBody(false)->get();
            $index = [];

            foreach ($messages as $message) {
                $index[(int) $message->getUid()] = $message->getFlags()->has('seen');
            }

            return $index;
        } catch (Throwable $e) {
            throw new ImapException($this->friendly($e), previous: $e);
        }
    }

    public function fetch(string $folder, array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        try {
            $messages = $this->folder($folder)->messages()->whereUidIn($uids)->leaveUnread()->get();
            $out = [];

            foreach ($messages as $message) {
                $out[] = $this->convert($message);
            }

            return $out;
        } catch (Throwable $e) {
            throw new ImapException($this->friendly($e), previous: $e);
        }
    }

    public function setSeen(string $folder, int $uid, bool $seen): void
    {
        $message = $this->one($folder, $uid);

        if ($message !== null) {
            $seen ? $message->setFlag('Seen') : $message->unsetFlag('Seen');
        }
    }

    public function move(string $folder, int $uid, string $to): ?int
    {
        $message = $this->one($folder, $uid);
        $moved = $message?->move($to, true);

        return $moved !== null ? (int) $moved->getUid() : null;
    }

    public function delete(string $folder, int $uid): bool
    {
        $message = $this->one($folder, $uid);

        return $message !== null && $message->delete(true);
    }

    public function close(): void
    {
        try {
            $this->client->disconnect();
        } catch (Throwable) {
            // Already closed.
        }
    }

    private function folder(string $path): Folder
    {
        $folder = $this->client->getFolderByPath($path);

        if ($folder === null) {
            throw new ImapException(__('The folder :folder was not found on the mail server.', ['folder' => $path]));
        }

        return $folder;
    }

    private function one(string $folder, int $uid): ?Message
    {
        try {
            return $this->folder($folder)->messages()->whereUid($uid)->leaveUnread()->get()->first();
        } catch (Throwable $e) {
            throw new ImapException($this->friendly($e), previous: $e);
        }
    }

    private function role(Folder $folder): ?string
    {
        $name = strtolower($folder->name);

        if (strtoupper($folder->path) === 'INBOX') {
            return 'inbox';
        }

        return match (true) {
            in_array($name, ['drafts', 'draft'], true) => 'drafts',
            in_array($name, ['spam', 'junk', 'junk e-mail', 'junk email', 'bulk mail'], true) => 'spam',
            in_array($name, ['trash', 'deleted', 'deleted items', 'deleted messages', 'bin'], true) => 'trash',
            default => null,
        };
    }

    private function convert(Message $message): RemoteMessage
    {
        $from = $this->first($message->getFrom());
        $date = $this->first($message->getDate());
        $attachments = [];

        foreach ($message->getAttachments() as $attachment) {
            $content = (string) $attachment->getContent();
            $attachments[] = [
                'name' => (string) ($attachment->getName() ?: 'attachment'),
                'mime' => (string) ($attachment->getMimeType() ?: 'application/octet-stream'),
                'content' => $content,
                'cid' => $attachment->getId() ? trim((string) $attachment->getId(), '<>') : null,
                'inline' => strtolower((string) $attachment->getDisposition()) === 'inline',
            ];
        }

        $replyTo = $this->first($message->getReplyTo());

        return new RemoteMessage(
            uid: (int) $message->getUid(),
            messageId: ($id = trim((string) $message->getMessageId())) !== '' ? trim($id, '<>') : null,
            fromEmail: $from?->mail ? strtolower((string) $from->mail) : null,
            fromName: $from?->personal ? (string) $from->personal : null,
            to: $this->addresses($message->getTo()),
            cc: $this->addresses($message->getCc()),
            replyTo: $replyTo?->mail ? strtolower((string) $replyTo->mail) : null,
            subject: trim((string) $message->getSubject()),
            date: $date ? CarbonImmutable::parse((string) $date) : null,
            text: $message->hasTextBody() ? (string) $message->getTextBody() : null,
            html: $message->hasHTMLBody() ? (string) $message->getHTMLBody() : null,
            attachments: $attachments,
            seen: $message->getFlags()->has('seen'),
        );
    }

    /** The first value of a header, or null when the email does not have it (the library returns false). */
    private function first(mixed $attribute): mixed
    {
        return $attribute->all()[0] ?? null;
    }

    /** @return list<string> */
    private function addresses(mixed $attribute): array
    {
        $out = [];

        foreach ($attribute->all() as $address) {
            if (! empty($address->mail)) {
                $out[] = strtolower((string) $address->mail);
            }
        }

        return $out;
    }

    private function friendly(Throwable $e): string
    {
        $class = class_basename($e);

        return match (true) {
            str_contains($class, 'Auth') || preg_match('/auth|credential|login|password/i', $e->getMessage()) === 1 => __('The mail server rejected the mailbox login. Check the mailbox password.'),
            str_contains($class, 'Connection') => __('The mail server could not be reached. Check the IMAP server and port.'),
            default => __('The mail server returned an error (:type).', ['type' => $class]),
        };
    }
}
