<?php

namespace App\Support\Presenters;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadContact;
use App\Models\LeadFollowUp;
use App\Models\LeadNote;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class LeadPresenter
{
    /** @return array<string, mixed> */
    public static function row(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'lead_number' => $lead->lead_number,
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'country' => $lead->country?->name,
            'service' => $lead->service?->name,
            'source' => $lead->source->label(),
            'status' => $lead->status->value,
            'status_label' => $lead->status->label(),
            'status_tone' => $lead->status->tone(),
            'counselor' => $lead->counselor?->name,
            'next_follow_up_at' => $lead->next_follow_up_at?->toIso8601String(),
            'follow_up_overdue' => $lead->next_follow_up_at !== null && $lead->next_follow_up_at->isPast(),
            'is_duplicate' => $lead->duplicate_of_id !== null,
            'created_at' => $lead->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Lead $lead, User $actor): array
    {
        return [
            ...self::row($lead),
            'country_id' => $lead->country_id,
            'service_id' => $lead->service_id,
            'counselor_id' => $lead->counselor_id,
            'contact_preference' => $lead->contact_preference?->value,
            'contact_preference_label' => $lead->contact_preference?->label(),
            'message' => $lead->message,
            'source_value' => $lead->source->value,
            'assigned_at' => $lead->assigned_at?->toIso8601String(),
            'last_contacted_at' => $lead->last_contacted_at?->toIso8601String(),
            'consent_at' => $lead->consent_at?->toIso8601String(),
            'converted_at' => $lead->converted_at?->toIso8601String(),
            'student' => $lead->student ? ['id' => $lead->student->id, 'number' => $lead->student->student_number] : null,
            'duplicate_of' => $lead->duplicateOf ? [
                'id' => $lead->duplicateOf->id,
                'number' => $lead->duplicateOf->lead_number,
                'name' => $lead->duplicateOf->name,
                'visible' => $actor->can('view', $lead->duplicateOf),
            ] : null,
            'creator' => $lead->creator?->name,
            'can' => [
                'update' => $actor->can('update', $lead) && $lead->status !== LeadStatus::Converted,
                'assign' => $actor->can('assign', $lead) && $lead->status !== LeadStatus::Converted,
                'transfer' => $actor->can('transfer', $lead) && $lead->status !== LeadStatus::Converted,
                'convert' => $actor->can('convert', $lead) && $lead->status !== LeadStatus::Converted,
                'delete' => $actor->can('delete', $lead),
            ],
        ];
    }

    /**
     * One chronological feed of everything that happened to the lead.
     *
     * Each entry: id, type, icon, title, body, by, at (ISO 8601).
     *
     * @return list<array<string, mixed>>
     */
    public static function timeline(Lead $lead): array
    {
        $items = collect();

        foreach ($lead->notes as $note) {
            /** @var LeadNote $note */
            $items->push(['id' => "note-{$note->id}", 'type' => 'note', 'icon' => 'sticky_note_2', 'title' => __('Note'), 'body' => $note->body, 'by' => $note->user?->name, 'at' => $note->created_at]);
        }

        foreach ($lead->contacts as $contact) {
            /** @var LeadContact $contact */
            $items->push(['id' => "contact-{$contact->id}", 'type' => 'contact', 'icon' => $contact->channel->icon(), 'title' => __('Contacted by :channel', ['channel' => mb_strtolower($contact->channel->label())]), 'body' => $contact->summary, 'by' => $contact->user?->name, 'at' => $contact->contacted_at]);
        }

        foreach ($lead->assignments as $a) {
            /** @var LeadAssignment $a */
            $title = match ($a->type) {
                'assigned' => __('Assigned to :name', ['name' => $a->toCounselor->name ?? '—']),
                'transferred' => __('Transferred from :from to :to', ['from' => $a->fromCounselor->name ?? '—', 'to' => $a->toCounselor->name ?? '—']),
                default => __('Unassigned from :name', ['name' => $a->fromCounselor->name ?? '—']),
            };
            $items->push(['id' => "assign-{$a->id}", 'type' => 'assignment', 'icon' => 'swap_horiz', 'title' => $title, 'body' => $a->note, 'by' => $a->assigner?->name, 'at' => $a->created_at]);
        }

        foreach ($lead->followUps as $f) {
            /** @var LeadFollowUp $f */
            if ($f->completed_at) {
                $items->push(['id' => "followup-done-{$f->id}", 'type' => 'followup_done', 'icon' => 'task_alt', 'title' => __('Follow-up completed'), 'body' => $f->outcome, 'by' => null, 'at' => $f->completed_at]);
            }
        }

        $statusChanges = Activity::query()
            ->whereMorphedTo('subject', $lead)
            ->where('event', 'updated')
            ->with('causer:id,name')
            ->get()
            ->filter(fn (Activity $a) => isset($a->attribute_changes['attributes']['status']))
            // Assigned / Converted are implied by the assignment and conversion entries.
            ->reject(fn (Activity $a) => in_array($a->attribute_changes['attributes']['status'], [LeadStatus::Assigned->value, LeadStatus::Converted->value], true));

        foreach ($statusChanges as $a) {
            $new = LeadStatus::tryFrom((string) $a->attribute_changes['attributes']['status']);
            $items->push(['id' => "status-{$a->id}", 'type' => 'status', 'icon' => 'flag', 'title' => __('Status changed to :status', ['status' => $new?->label() ?? '—']), 'body' => null, 'by' => $a->causer?->getAttribute('name'), 'at' => $a->created_at]);
        }

        $items->push(['id' => 'created', 'type' => 'created', 'icon' => 'add_circle', 'title' => __('Lead created · :source', ['source' => $lead->source->label()]), 'body' => null, 'by' => $lead->creator?->name, 'at' => $lead->created_at]);

        // Newest first; on a tie, "created" sinks to the bottom and later
        // pushes (assignments, status changes) rise above earlier ones.
        $ordered = $items->values()->map(fn ($i, $seq) => [...$i, 'seq' => $i['type'] === 'created' ? -1 : $seq]);

        return array_values($ordered
            ->sort(fn ($a, $b) => [$b['at']?->getTimestamp() ?? 0, $b['seq']] <=> [$a['at']?->getTimestamp() ?? 0, $a['seq']])
            ->map(function ($i) {
                unset($i['seq']);

                return $i;
            })
            ->map(fn ($i) => [...$i, 'at' => $i['at']?->toIso8601String()])
            ->all());
    }

    /** @return list<array<string, mixed>> */
    public static function openFollowUps(Lead $lead): array
    {
        return array_values($lead->followUps
            ->whereNull('completed_at')
            ->sortBy('due_at')
            ->map(fn (LeadFollowUp $f) => [
                'id' => $f->id,
                'due_at' => $f->due_at->toIso8601String(),
                'overdue' => $f->due_at->isPast(),
                'note' => $f->note,
                'owner' => $f->user?->name,
            ])
            ->all());
    }
}
