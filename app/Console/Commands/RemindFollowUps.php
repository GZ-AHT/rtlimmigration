<?php

namespace App\Console\Commands;

use App\Models\LeadFollowUp;
use App\Notifications\FollowUpDue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Notifies counselors when a lead follow-up falls due (REQ §7 "Follow-up
 * Date", §8.1 "Follow-up Tasks"). Each follow-up is reminded once.
 */
#[Signature('leads:remind-follow-ups')]
#[Description('Send in-app reminders for lead follow-ups that are now due')]
class RemindFollowUps extends Command
{
    public function handle(): int
    {
        $sent = 0;

        LeadFollowUp::query()
            ->whereNull('completed_at')
            ->whereNull('reminded_at')
            ->where('due_at', '<=', now())
            ->whereHas('lead', fn ($q) => $q->whereNull('deleted_at'))
            ->with(['lead', 'user'])
            ->chunkById(100, function ($followUps) use (&$sent) {
                foreach ($followUps as $followUp) {
                    /** @var LeadFollowUp $followUp */
                    $followUp->user?->notify(new FollowUpDue($followUp));
                    $followUp->forceFill(['reminded_at' => now()])->save();
                    $sent++;
                }
            });

        $this->info("Sent {$sent} follow-up reminder(s).");

        return self::SUCCESS;
    }
}
