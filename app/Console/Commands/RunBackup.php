<?php

namespace App\Console\Commands;

use App\Support\Backups\BackupService;
use App\Support\System\SystemSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * REQ §23.5 automated backup. `--scheduled` (used by the scheduler) follows the
 * System → Settings → Backups schedule; without it a backup is made now.
 */
#[Signature('backup:run {--scheduled : Only run when the saved schedule says so}')]
#[Description('Back up the database (and uploaded files), then delete old automatic backups')]
class RunBackup extends Command
{
    public function handle(BackupService $backups): int
    {
        $schedule = (string) SystemSettings::get('backups.schedule', 'off');

        if ($this->option('scheduled') && ($schedule === 'off' || ($schedule === 'weekly' && ! now()->isSunday()))) {
            return self::SUCCESS;
        }

        $trigger = $this->option('scheduled') ? 'scheduled' : 'manual';
        $made = [$backups->create('database', $trigger)];

        if (SystemSettings::get('backups.include_files', true)) {
            $made[] = $backups->create('files', $trigger);
        }

        foreach ($made as $backup) {
            $backup->status === 'done' ? $this->info("{$backup->filename} created.") : $this->error("{$backup->type} backup failed: {$backup->error}");
        }

        $backups->prune();

        return collect($made)->every(fn ($b) => $b->status === 'done') ? self::SUCCESS : self::FAILURE;
    }
}
