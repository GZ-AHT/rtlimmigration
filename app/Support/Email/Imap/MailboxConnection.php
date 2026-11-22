<?php

namespace App\Support\Email\Imap;

/** What the dashboard needs from one mailbox on the mail server. */
interface MailboxConnection
{
    /**
     * Real folder names on the server, by role.
     *
     * @return array<string, string> role (inbox|drafts|spam|trash) => folder name
     */
    public function folders(): array;

    /**
     * Every UID in the folder, with whether it has been read.
     *
     * @return array<int, bool> uid => seen
     */
    public function index(string $folder): array;

    /**
     * Full messages for the given UIDs (never more than a small batch).
     *
     * @param  list<int>  $uids
     * @return list<RemoteMessage>
     */
    public function fetch(string $folder, array $uids): array;

    public function setSeen(string $folder, int $uid, bool $seen): void;

    /** Moves a message; returns its new UID in the target folder, or null if the server did not do it. */
    public function move(string $folder, int $uid, string $to): ?int;

    public function delete(string $folder, int $uid): bool;

    public function close(): void;
}
