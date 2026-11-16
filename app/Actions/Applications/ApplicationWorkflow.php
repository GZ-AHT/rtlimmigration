<?php

namespace App\Actions\Applications;

use App\Actions\Payments\PaymentLedger;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentStatus;
use App\Enums\StageKind;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\Country;
use App\Models\Document;
use App\Models\PaymentRequest;
use App\Models\Student;
use App\Models\StudentFile;
use App\Models\User;
use App\Models\VisaType;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use App\Notifications\ApplicationUpdated;
use App\Support\Numbering\NumberGenerator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every application status change goes through here (REQ §13, §13.1–13.3;
 * roadmap Q14: one engine for all visa types):
 *
 *  - the move is checked against the application's own workflow;
 *  - decision, office-visit and final stages apply their rules;
 *  - each change writes a history row with who, when, remarks and any
 *    supporting documents, all in one transaction;
 *  - the student is told, and the history records that they were.
 *
 * Payments are a separate state machine (REQ §30). The only link is the
 * convenience of raising the visa type's fee invoice from an application.
 */
class ApplicationWorkflow
{
    public function __construct(private NumberGenerator $numbers) {}

    /**
     * @param  array{title?: string|null, organisation?: string|null, reference_number?: string|null, scheduled_at?: string|null, notes?: string|null}  $details
     */
    public function start(Student $student, VisaType $type, ?Country $country, ?StudentFile $file, array $details, User $actor): Application
    {
        if ($file !== null && $file->student_id !== $student->id) {
            throw ValidationException::withMessages(['student_file_id' => __('That file does not belong to this student.')]);
        }

        if (! $type->is_active) {
            throw ValidationException::withMessages(['visa_type_id' => __('That visa type is not in use.')]);
        }

        $workflow = Workflow::resolve($type->id, $country?->id);
        $first = $workflow?->stages()->first();

        if ($workflow === null || $first === null) {
            throw ValidationException::withMessages(['visa_type_id' => __('No workflow with stages is set up for this visa type yet. Ask an administrator to add one.')]);
        }

        [$application, $history] = DB::transaction(function () use ($student, $type, $country, $file, $details, $actor, $workflow, $first) {
            $application = Application::create([
                'application_number' => $this->numbers->next('application'),
                'student_id' => $student->id,
                'student_file_id' => $file?->id,
                'visa_type_id' => $type->id,
                'country_id' => $country?->id,
                'workflow_id' => $workflow->id,
                'current_stage_id' => $first->id,
                'status' => ApplicationStatus::InProgress,
                'title' => $details['title'] ?? null,
                'organisation' => $details['organisation'] ?? null,
                'reference_number' => $details['reference_number'] ?? null,
                'scheduled_at' => $details['scheduled_at'] ?? null,
                'notes' => $details['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $history = $application->history()->create([
                'to_stage_id' => $first->id,
                'to_stage_name' => $first->name,
                'event' => 'created',
                'note' => __('Application started'),
                'changed_by' => $actor->id,
            ]);

            return [$application, $history];
        });

        $this->notify($application, $history, force: true);

        return $application;
    }

    /**
     * Move to a stage, or post an update on the current one.
     *
     * @param  array{note?: string|null, decision?: string|null, rejection_reason?: string|null, office_visit_at?: string|null, result?: string|null}  $input
     * @param  list<UploadedFile>  $files
     */
    public function advance(Application $application, WorkflowStage $stage, array $input, array $files, User $actor): ApplicationStatusHistory
    {
        $note = filled($input['note'] ?? null) ? trim((string) $input['note']) : null;
        $stored = array_map(fn (UploadedFile $file) => $this->store($application, $file), $files);

        try {
            $history = DB::transaction(function () use ($application, $stage, $input, $note, $stored, $actor) {
                $application = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();

                if (! $application->status->isActive()) {
                    throw ValidationException::withMessages(['stage_id' => __('This application is :status and can no longer be updated.', ['status' => strtolower($application->status->label())])]);
                }

                if ($stage->workflow_id !== $application->workflow_id || $stage->trashed()) {
                    throw ValidationException::withMessages(['stage_id' => __('That stage is not part of this application\'s workflow.')]);
                }

                $from = $application->stage;
                $sameStage = $from?->id === $stage->id;

                if ($sameStage && $note === null && $stored === []) {
                    throw ValidationException::withMessages(['note' => __('Add a note or a document to post an update on the current stage.')]);
                }

                $event = $sameStage ? 'update' : 'stage';

                if (! $sameStage) {
                    $event = $this->applyStageRules($application, $stage, $input, $stored !== [], $actor);
                    $application->current_stage_id = $stage->id;
                }

                if (array_key_exists('result', $input) && filled($input['result'])) {
                    $application->result = mb_substr((string) $input['result'], 0, 255);
                }

                $application->save();

                $history = $application->history()->create([
                    'from_stage_id' => $from?->id,
                    'from_stage_name' => $from?->name,
                    'to_stage_id' => $stage->id,
                    'to_stage_name' => $stage->name,
                    'event' => $event,
                    'note' => $note,
                    'changed_by' => $actor->id,
                ]);

                foreach ($stored as $file) {
                    $this->attach($application, $history, $file, $stage, $actor);
                }

                return $history;
            });
        } catch (\Throwable $e) {
            foreach ($stored as $file) {
                Storage::disk($file['disk'])->delete($file['path']);
            }

            throw $e;
        }

        // A decision always reaches the student, whatever the stage says.
        $this->notify($application->refresh(), $history, force: $history->event === 'decision');

        return $history;
    }

    public function withdraw(Application $application, string $reason, User $actor): void
    {
        $history = DB::transaction(function () use ($application, $reason, $actor) {
            $application = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (! $application->status->isActive()) {
                throw ValidationException::withMessages(['reason' => __('This application is already :status.', ['status' => strtolower($application->status->label())])]);
            }

            $stage = $application->stage;
            $application->update(['status' => ApplicationStatus::Withdrawn, 'completed_at' => now()]);

            return $application->history()->create([
                'from_stage_id' => $stage?->id,
                'from_stage_name' => $stage?->name,
                'to_stage_id' => $stage?->id,
                'to_stage_name' => __('Withdrawn'),
                'event' => 'withdrawn',
                'note' => $reason,
                'changed_by' => $actor->id,
            ]);
        });

        $this->notify($application->refresh(), $history, force: true);
    }

    /**
     * REQ §17 "Visa Fees": raise the visa type's fee as an invoice. From
     * there it is an ordinary payment request (REQ §12).
     */
    public function raiseFee(Application $application, PaymentLedger $ledger, User $actor): PaymentRequest
    {
        $type = $application->visaType;

        if ($type->paymentType === null || ! $type->fee_amount) {
            throw ValidationException::withMessages(['fee' => __('No fee is set for :type. An administrator can set one under Visa types.', ['type' => $type->name])]);
        }

        return $ledger->createRequest(
            $application->student,
            $type->paymentType,
            $type->fee_amount,
            CarbonImmutable::today()->addDays((int) config('payments.default_due_days', 7)),
            __(':type · :number', ['type' => $type->name, 'number' => $application->application_number]),
            $application->file,
            $actor,
        );
    }

    /**
     * @param  array{note?: string|null, decision?: string|null, rejection_reason?: string|null, office_visit_at?: string|null, result?: string|null}  $input
     * @return string the history event name
     */
    private function applyStageRules(Application $application, WorkflowStage $stage, array $input, bool $hasDocument, User $actor): string
    {
        switch ($stage->kind) {
            case StageKind::Decision:
                // REQ §27: recording a decision is its own permission.
                if (! $actor->can('applications.decide')) {
                    throw ValidationException::withMessages(['stage_id' => __('You are not allowed to record visa decisions.')]);
                }

                $decision = $input['decision'] ?? null;

                if (! in_array($decision, ['approved', 'rejected'], true)) {
                    throw ValidationException::withMessages(['decision' => __('Say whether the application was approved or rejected.')]);
                }

                if ($decision === 'rejected' && blank($input['rejection_reason'] ?? null)) {
                    throw ValidationException::withMessages(['rejection_reason' => __('Record the reason given for the rejection.')]);
                }

                // REQ §13.1 / §13.2 "Upload approval / rejection document".
                if (! $hasDocument) {
                    throw ValidationException::withMessages(['files' => __('Upload the :kind document.', ['kind' => $decision === 'approved' ? __('approval') : __('rejection')])]);
                }

                $application->fill([
                    'decision' => $decision,
                    'decided_at' => now(),
                    'decided_by' => $actor->id,
                    'rejection_reason' => $decision === 'rejected' ? trim((string) $input['rejection_reason']) : null,
                    'status' => $decision === 'approved' ? ApplicationStatus::Approved : ApplicationStatus::Rejected,
                ]);

                return 'decision';

            case StageKind::VisitRequested:
                $application->fill([
                    'office_visit_requested_at' => now(),
                    'office_visit_at' => filled($input['office_visit_at'] ?? null) ? CarbonImmutable::parse((string) $input['office_visit_at']) : null,
                    'office_visit_completed_at' => null,
                ]);

                return 'stage';

            case StageKind::VisitCompleted:
                $application->office_visit_completed_at = now();

                return 'stage';

            case StageKind::Final:
                // A case cannot be closed as complete with the decision still unrecorded.
                if ($application->decision === null && $application->workflow->stages()->where('kind', StageKind::Decision->value)->exists()) {
                    throw ValidationException::withMessages(['stage_id' => __('Record the decision before completing the application.')]);
                }

                $application->fill(['status' => ApplicationStatus::Completed, 'completed_at' => now()]);

                return 'stage';

            default:
                return 'stage';
        }
    }

    /** @return array{disk: string, path: string, name: string, mime: string, size: int} */
    private function store(Application $application, UploadedFile $file): array
    {
        $disk = (string) config('documents.disk');
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $path = $file->storeAs(config('documents.directory').'/'.$application->student_id, Str::uuid()->toString().'.'.$extension, $disk);

        if ($path === false) {
            throw ValidationException::withMessages(['files' => __('The file could not be saved. Please try again.')]);
        }

        return ['disk' => $disk, 'path' => $path, 'name' => mb_substr($file->getClientOriginalName(), 0, 255), 'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize()];
    }

    /**
     * Supporting documents are issued or checked by staff, so they are
     * filed as verified and skip the review queue.
     *
     * @param  array{disk: string, path: string, name: string, mime: string, size: int}  $file
     */
    private function attach(Application $application, ApplicationStatusHistory $history, array $file, WorkflowStage $stage, User $actor): void
    {
        Document::create([
            'student_id' => $application->student_id,
            'student_file_id' => $application->student_file_id,
            'application_id' => $application->id,
            'application_status_history_id' => $history->id,
            'title' => mb_substr($stage->name.': '.$file['name'], 0, 120),
            'disk' => $file['disk'],
            'path' => $file['path'],
            'original_name' => $file['name'],
            'mime_type' => $file['mime'],
            'size' => $file['size'],
            'status' => DocumentStatus::Verified,
            'uploaded_by' => $actor->id,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'is_current' => true,
        ]);
    }

    /**
     * Tell the student (when the stage asks for it) and the counselor if
     * someone else made the change. REQ §13.3 "Notification History": the
     * history row records when the student was told.
     */
    private function notify(Application $application, ApplicationStatusHistory $history, bool $force): void
    {
        $application->loadMissing(['student.user', 'student.counselor', 'visaType', 'stage']);
        $history->setRelation('application', $application);

        $stage = $history->to_stage_id ? WorkflowStage::withTrashed()->find($history->to_stage_id) : null;
        $tellStudent = $force || $stage === null || $stage->notify_student;
        $recipients = collect();

        if ($tellStudent) {
            $recipients->push($application->student->user);
            $history->forceFill(['student_notified_at' => now()])->save();
        }

        $counselor = $application->student->counselor;

        if ($counselor !== null && $counselor->id !== $history->changed_by) {
            $recipients->push($counselor);
        }

        Notification::send($recipients->filter()->unique('id'), new ApplicationUpdated($history));
    }

    /** For callers that only have a date string from a form. */
    public static function parseDate(?string $value): ?CarbonInterface
    {
        return filled($value) ? CarbonImmutable::parse((string) $value) : null;
    }
}
