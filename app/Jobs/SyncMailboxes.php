<?php

namespace App\Jobs;

use App\Support\Email\CpanelException;
use App\Support\Email\EmailSettings;
use App\Support\Email\MailboxManager;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Keeps the mailbox list, storage use and suspensions in step with cPanel
 * (REQ §19.2) without anyone pressing "Sync". Scheduled every 30 minutes;
 * the same sync the manual button runs, so mailboxes are matched by address
 * and never duplicated. Needs the scheduler and a queue worker.
 */
class SyncMailboxes implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Frees the "already queued" lock if a worker dies mid-sync. */
    public int $uniqueFor = 900;

    public function handle(MailboxManager $mailboxes, EmailSettings $settings): void
    {
        if (! $settings->cpanelConfigured()) {
            return;
        }

        try {
            $mailboxes->sync(null);
        } catch (CpanelException $e) {
            // A busy or unreachable server should not fill the failed-jobs table; the next run tries again.
            report($e);
        }
    }
}
