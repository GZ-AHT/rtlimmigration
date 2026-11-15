<?php

namespace App\Actions\Leads;

use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assigns, transfers or unassigns a lead (REQ §7 "Lead Assignment",
 * "Counselor Assignment", "Lead Transfer"). Every change is kept in
 * lead_assignments and the new counselor is notified.
 */
class AssignLead
{
    public function __invoke(Lead $lead, ?User $counselor, User $actor, ?string $note = null): void
    {
        if ($counselor !== null && (! $counselor->hasRole(Role::Counselor->value) || ! $counselor->isActive())) {
            throw ValidationException::withMessages(['counselor_id' => __('Leads can only be assigned to active counselors.')]);
        }

        if ($lead->status === LeadStatus::Converted) {
            throw ValidationException::withMessages(['counselor_id' => __('This lead has already been converted to a student.')]);
        }

        $from = $lead->counselor_id;

        if ($from === $counselor?->id) {
            return;
        }

        $type = match (true) {
            $counselor === null => 'unassigned',
            $from === null => 'assigned',
            default => 'transferred',
        };

        DB::transaction(function () use ($lead, $counselor, $actor, $note, $from, $type) {
            $lead->counselor_id = $counselor?->id;
            $lead->assigned_at = $counselor ? now() : null;

            if ($counselor && $lead->status === LeadStatus::New) {
                $lead->status = LeadStatus::Assigned;
            } elseif (! $counselor && $lead->status === LeadStatus::Assigned) {
                $lead->status = LeadStatus::New;
            }

            $lead->save();

            $lead->assignments()->create([
                'from_counselor_id' => $from,
                'to_counselor_id' => $counselor?->id,
                'assigned_by' => $actor->id,
                'type' => $type,
                'note' => $note,
            ]);
        });

        if ($counselor && $counselor->isNot($actor)) {
            $counselor->notify(new LeadAssigned($lead, $type === 'transferred'));
        }
    }
}
