<?php

namespace App\Jobs;

use App\Models\EmailAccount;
use App\Support\Email\Imap\ImapConnector;
use App\Support\Email\Imap\ImapException;
use App\Support\Email\InboxSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Brings new mail from every cPanel mailbox into the dashboard (IMAP).
 * Scheduled every five minutes; the "Sync" button runs the same code, and
 * messages are matched, never duplicated. Needs the scheduler and a queue worker.
 */
class SyncInboxes implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 280;

    /** Frees the "already queued" lock if a worker dies mid-sync. */
    public int $uniqueFor = 600;

    public function handle(InboxSync $sync, ImapConnector $connector): void
    {
        EmailAccount::query()->where('status', 'active')->where('on_server', true)->whereNotNull('password')->orderBy('id')
            ->each(function (EmailAccount $account) use ($sync, $connector) {
                if (! $connector->ready($account)) {
                    return;
                }

                try {
                    $sync->sync($account);
                } catch (ImapException $e) {
                    // The reason is kept for the Inbox screen; one broken mailbox must not stop the others.
                    report($e);
                }
            });
    }
}
