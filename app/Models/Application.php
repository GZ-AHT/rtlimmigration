<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One visa (or work & examination) application for a student (REQ §13).
 * Its progress is a stage of its workflow; payments stay a separate state
 * machine (REQ §30), linked only through the student file.
 *
 * @property int $id
 * @property string $application_number
 * @property int $student_id
 * @property int|null $student_file_id
 * @property int $visa_type_id
 * @property int|null $country_id
 * @property int $workflow_id
 * @property int|null $current_stage_id
 * @property ApplicationStatus $status
 * @property string|null $title
 * @property string|null $organisation
 * @property string|null $reference_number
 * @property CarbonInterface|null $scheduled_at
 * @property string|null $result
 * @property string|null $decision
 * @property CarbonInterface|null $decided_at
 * @property int|null $decided_by
 * @property string|null $rejection_reason
 * @property CarbonInterface|null $office_visit_requested_at
 * @property CarbonInterface|null $office_visit_at
 * @property CarbonInterface|null $office_visit_completed_at
 * @property CarbonInterface|null $completed_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Student $student
 * @property-read StudentFile|null $file
 * @property-read VisaType $visaType
 * @property-read Country|null $country
 * @property-read Workflow $workflow
 * @property-read WorkflowStage|null $stage
 * @property-read User|null $creator
 */
#[Fillable([
    'application_number', 'student_id', 'student_file_id', 'visa_type_id', 'country_id', 'workflow_id', 'current_stage_id',
    'status', 'title', 'organisation', 'reference_number', 'scheduled_at', 'result', 'decision', 'decided_at', 'decided_by',
    'rejection_reason', 'office_visit_requested_at', 'office_visit_at', 'office_visit_completed_at', 'completed_at', 'notes', 'created_by',
])]
class Application extends Model
{
    protected $attributes = ['status' => 'in_progress'];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'scheduled_at' => 'datetime',
            'decided_at' => 'datetime',
            'office_visit_requested_at' => 'datetime',
            'office_visit_at' => 'datetime',
            'office_visit_completed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<StudentFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(StudentFile::class, 'student_file_id');
    }

    /** @return BelongsTo<VisaType, $this> */
    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Workflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /** @return BelongsTo<WorkflowStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'current_stage_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** @return HasMany<ApplicationStatusHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class)->latest('created_at')->latest('id');
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Applications the user may see: those of students in their scope.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('student', fn (Builder $q) => $q->visibleTo($user));
    }
}
