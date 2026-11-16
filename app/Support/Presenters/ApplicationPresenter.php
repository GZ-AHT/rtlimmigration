<?php

namespace App\Support\Presenters;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\VisaRequirement;
use App\Models\WorkflowStage;
use Closure;

class ApplicationPresenter
{
    /** @return array<string, mixed> */
    public static function row(Application $a): array
    {
        $kind = $a->visaType->kind;

        return [
            'id' => $a->id,
            'application_number' => $a->application_number,
            'student' => $a->relationLoaded('student') ? $a->student->user->name : null,
            'student_number' => $a->relationLoaded('student') ? $a->student->student_number : null,
            'visa_type' => $a->visaType->name,
            'kind' => $kind->value,
            'country' => $a->country?->name,
            'file_number' => $a->file?->file_number,
            'title' => $a->title,
            'organisation' => $a->organisation,
            'reference_number' => $a->reference_number,
            'scheduled_at' => $a->scheduled_at?->toIso8601String(),
            'result' => $a->result,
            'stage' => $a->stage?->name,
            'stage_kind' => $a->stage?->kind->value,
            'status' => $a->status->value,
            'status_label' => $a->status->label(),
            'status_tone' => $a->status->tone(),
            'decision' => $a->decision,
            'decided_at' => $a->decided_at?->toIso8601String(),
            'rejection_reason' => $a->rejection_reason,
            'office_visit_requested_at' => $a->office_visit_requested_at?->toIso8601String(),
            'office_visit_at' => $a->office_visit_at?->toIso8601String(),
            'office_visit_completed_at' => $a->office_visit_completed_at?->toIso8601String(),
            'completed_at' => $a->completed_at?->toIso8601String(),
            'labels' => ['title' => $kind->titleLabel(), 'organisation' => $kind->organisationLabel(), 'schedule' => $kind->scheduleLabel()],
            'created_at' => $a->created_at?->toIso8601String(),
            'updated_at' => $a->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The workflow as a progress strip: every stage before the current one
     * counts as done.
     *
     * @return list<array{id: int, name: string, kind: string, description: string|null, state: 'done'|'current'|'upcoming'}>
     */
    public static function stages(Application $a): array
    {
        $stages = $a->workflow->stages->values();
        $currentIndex = $stages->search(fn (WorkflowStage $s) => $s->id === $a->current_stage_id);
        $finished = $a->status === ApplicationStatus::Completed;
        $rows = [];

        foreach ($stages as $i => $stage) {
            $rows[] = [
                'id' => $stage->id,
                'name' => $stage->name,
                'kind' => $stage->kind->value,
                'description' => $stage->description,
                'state' => match (true) {
                    ! is_int($currentIndex) => 'upcoming',
                    $finished || $i < $currentIndex => 'done',
                    $i === $currentIndex => 'current',
                    default => 'upcoming',
                },
            ];
        }

        return $rows;
    }

    /** Progress through the workflow, 0–100. */
    public static function progress(Application $a): int
    {
        $stages = self::stages($a);
        $done = count(array_filter($stages, fn (array $s) => $s['state'] === 'done'));
        $current = count(array_filter($stages, fn (array $s) => $s['state'] === 'current'));

        return $stages === [] ? 0 : (int) round(($done + $current) / count($stages) * 100);
    }

    /**
     * @param  Closure(Document): string  $documentUrl
     * @return array<string, mixed>
     */
    public static function history(ApplicationStatusHistory $h, Closure $documentUrl, bool $forStudent = false): array
    {
        return [
            'id' => $h->id,
            'event' => $h->event,
            'from' => $h->from_stage_name,
            'to' => $h->to_stage_name,
            'note' => $h->note,
            // Students see that staff updated the case, not which member of staff.
            'by' => $forStudent ? null : $h->changer?->name,
            'at' => $h->created_at->toIso8601String(),
            'notified' => $h->student_notified_at !== null,
            'documents' => $h->documents->map(fn (Document $d) => ['id' => $d->id, 'name' => $d->original_name, 'url' => $documentUrl($d)])->values(),
        ];
    }

    /**
     * REQ §17 "Required Documents": the visa type's checklist against what
     * the student has on file.
     *
     * @return list<array{category: string, status: string, label: string, tone: string}>
     */
    public static function checklist(Application $a): array
    {
        $current = $a->student->documents()->where('is_current', true)->whereNotNull('category_id')->get()->keyBy('category_id');

        return array_values($a->visaType->documentCategories->map(function (DocumentCategory $c) use ($current) {
            $document = $current->get($c->id);

            return [
                'category' => $c->name,
                'status' => $document ? $document->status->value : 'missing',
                'label' => $document ? $document->status->label() : 'Not uploaded',
                'tone' => $document ? $document->status->tone() : 'neutral',
            ];
        })->all());
    }

    /** @return list<array{title: string, description: string|null}> */
    public static function requirements(Application $a): array
    {
        return array_values($a->visaType->requirements
            ->filter(fn (VisaRequirement $r) => $r->country_id === null || $r->country_id === $a->country_id)
            ->map(fn (VisaRequirement $r) => ['title' => $r->title, 'description' => $r->description])
            ->all());
    }
}
