<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A destination country's skill migration assessment: its own questions
 * (from the shared bank, with weights), passing percentage and
 * recommendations. One per country; visitors reach it at /skill-migration/{slug}.
 *
 * @property int $id
 * @property int $country_id
 * @property string $slug
 * @property string $title
 * @property string|null $summary
 * @property string|null $intro
 * @property int $pass_percentage
 * @property int|null $minutes
 * @property string $status
 * @property CarbonInterface|null $published_at
 * @property-read Country $country
 * @property-read int|null $live_questions_count
 * @property-read int|null $completed_count
 */
#[Fillable(['country_id', 'slug', 'title', 'summary', 'intro', 'pass_percentage', 'minutes', 'status', 'published_at', 'created_by', 'updated_by'])]
class SkillAssessment extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published'];

    protected function casts(): array
    {
        return ['pass_percentage' => 'integer', 'minutes' => 'integer', 'published_at' => 'datetime'];
    }

    /**
     * Published assessments whose country is active (what visitors may see).
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')->whereHas('country', fn (Builder $q) => $q->where('is_active', true));
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->country->is_active;
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsToMany<SkillAssessmentQuestion, $this> */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(SkillAssessmentQuestion::class, 'skill_assessment_question_links')
            ->withPivot(['weight', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    /**
     * Questions a visitor would answer now: active ones with at least two options.
     *
     * @return BelongsToMany<SkillAssessmentQuestion, $this>
     */
    public function liveQuestions(): BelongsToMany
    {
        return $this->questions()->where('is_active', true)->has('options', '>=', 2);
    }

    /** @return HasMany<SkillAssessmentBand, $this> */
    public function bands(): HasMany
    {
        return $this->hasMany(SkillAssessmentBand::class)->orderByDesc('min_percent');
    }

    /** @return HasMany<SkillAssessmentSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(SkillAssessmentSubmission::class);
    }
}
