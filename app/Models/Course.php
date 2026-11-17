<?php

namespace App\Models;

use App\Enums\StudyLevel;
use App\Enums\VisaKind;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A programme at a university (REQ §15 Available Courses, Tuition Fees,
 * Admission Requirements, Application Deadlines, Scholarships, Intakes).
 *
 * @property int $id
 * @property int $university_id
 * @property string $name
 * @property string $slug
 * @property StudyLevel $level
 * @property string|null $subject
 * @property VisaKind $visa_kind
 * @property string|null $duration
 * @property string|null $tuition_fee
 * @property string|null $tuition_currency
 * @property string|null $tuition_period
 * @property list<int>|null $intakes
 * @property CarbonInterface|null $application_deadline
 * @property string|null $requirements
 * @property string|null $scholarship
 * @property bool $is_featured
 * @property bool $is_active
 * @property-read University $university
 */
#[Fillable(['university_id', 'name', 'slug', 'level', 'subject', 'visa_kind', 'duration', 'tuition_fee', 'tuition_currency', 'tuition_period', 'intakes', 'application_deadline', 'requirements', 'scholarship', 'is_featured', 'is_active'])]
class Course extends Model
{
    protected function casts(): array
    {
        return [
            'level' => StudyLevel::class,
            'visa_kind' => VisaKind::class,
            'intakes' => 'array',
            'application_deadline' => 'date',
            'tuition_fee' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return BelongsTo<University, $this> */
    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    /** "Jan, May, Sep" from month numbers. */
    public function intakeLabel(): string
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        return implode(', ', array_map(fn (int $m) => $months[$m - 1] ?? '', $this->intakes ?? []));
    }
}
