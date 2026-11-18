<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One visitor's assessment. The questions, options, weights, passing % and
 * recommendations are copied into `snapshot` when they start, so later
 * edits by admins can't change an assessment in progress or its result.
 *
 * @property int $id
 * @property string $token
 * @property int|null $skill_assessment_id
 * @property int|null $country_id
 * @property string $country_name
 * @property string $assessment_title
 * @property int|null $lead_id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string $status
 * @property bool $is_preview
 * @property array{pass_percentage: int, questions: list<array{id: int, text: string, help: string|null, category: string, weight: int, options: list<array{id: int, label: string, points: int}>}>, bands: list<array{min_percent: int, title: string, text: string}>} $snapshot
 * @property array<int, int>|null $answers
 * @property string|null $score_percent
 * @property bool|null $passed
 * @property list<array{category: string, percent: float}>|null $category_scores
 * @property string|null $band_title
 * @property string|null $band_text
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $created_at
 * @property-read SkillAssessment|null $assessment
 * @property-read Country|null $country
 * @property-read Lead|null $lead
 */
#[Fillable(['token', 'skill_assessment_id', 'country_id', 'country_name', 'assessment_title', 'lead_id', 'name', 'email', 'phone', 'status', 'is_preview', 'snapshot', 'answers', 'score_percent', 'passed', 'category_scores', 'band_title', 'band_text', 'ip_address', 'user_agent', 'completed_at'])]
class SkillAssessmentSubmission extends Model
{
    protected function casts(): array
    {
        return [
            'is_preview' => 'boolean',
            'snapshot' => 'array',
            'answers' => 'array',
            'score_percent' => 'decimal:2',
            'passed' => 'boolean',
            'category_scores' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /** @return BelongsTo<SkillAssessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(SkillAssessment::class, 'skill_assessment_id');
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
