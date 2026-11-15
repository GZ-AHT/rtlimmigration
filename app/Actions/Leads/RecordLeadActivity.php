<?php

namespace App\Actions\Leads;

use App\Enums\ContactChannel;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Day-to-day lead work: status, notes, contact history and follow-ups
 * (REQ §7). Each method keeps the lead's summary fields (status,
 * last_contacted_at, next_follow_up_at) consistent with the detail rows.
 */
class RecordLeadActivity
{
    public function changeStatus(Lead $lead, LeadStatus $status): void
    {
        if (! in_array($status, LeadStatus::manual(), true)) {
            throw ValidationException::withMessages(['status' => __('That status is set automatically and cannot be chosen by hand.')]);
        }

        if ($lead->status === LeadStatus::Converted) {
            throw ValidationException::withMessages(['status' => __('Converted leads cannot change status.')]);
        }

        $lead->update(['status' => $status]);
    }

    public function addNote(Lead $lead, User $actor, string $body): void
    {
        $lead->notes()->create(['user_id' => $actor->id, 'body' => $body]);
    }

    public function logContact(Lead $lead, User $actor, ContactChannel $channel, string $summary, CarbonInterface $at): void
    {
        DB::transaction(function () use ($lead, $actor, $channel, $summary, $at) {
            $lead->contacts()->create([
                'user_id' => $actor->id,
                'channel' => $channel,
                'summary' => $summary,
                'contacted_at' => $at,
            ]);

            $lead->last_contacted_at = $lead->last_contacted_at === null || $at->greaterThan($lead->last_contacted_at)
                ? $at
                : $lead->last_contacted_at;

            // First contact moves a fresh lead forward (REQ §11 step 3).
            if (in_array($lead->status, [LeadStatus::New, LeadStatus::Assigned], true)) {
                $lead->status = LeadStatus::Contacted;
            }

            $lead->save();
        });
    }

    public function scheduleFollowUp(Lead $lead, User $actor, CarbonInterface $dueAt, ?string $note): void
    {
        DB::transaction(function () use ($lead, $actor, $dueAt, $note) {
            $lead->followUps()->create([
                // Reminders go to the assigned counselor, or the scheduler.
                'user_id' => $lead->counselor_id ?? $actor->id,
                'due_at' => $dueAt,
                'note' => $note,
            ]);

            if (! $lead->status->isClosed()) {
                $lead->status = LeadStatus::FollowUp;
            }

            $this->refreshNextFollowUp($lead);
        });
    }

    public function completeFollowUp(LeadFollowUp $followUp, User $actor, ?string $outcome): void
    {
        if ($followUp->completed_at !== null) {
            return;
        }

        DB::transaction(function () use ($followUp, $actor, $outcome) {
            $followUp->update(['completed_at' => now(), 'completed_by' => $actor->id, 'outcome' => $outcome]);
            $this->refreshNextFollowUp($followUp->lead);
        });
    }

    private function refreshNextFollowUp(Lead $lead): void
    {
        $lead->next_follow_up_at = $lead->followUps()->whereNull('completed_at')->min('due_at');
        $lead->save();
    }
}
