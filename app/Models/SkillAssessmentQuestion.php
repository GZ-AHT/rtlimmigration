<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A multiple-choice question in the shared bank. Each answer option is worth
 * points; a question can be used by one or several countries' assessments.
 *
 * @property int $id
 * @property int|null $skill_assessment_category_id
 * @property string $text
 * @property string|null $help
 * @property bool $is_active
 * @property-read SkillAssessmentCategory|null $category
 */
#[Fillable(['skill_assessment_category_id', 'text', 'help', 'is_active'])]
class SkillAssessmentQuestion extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<SkillAssessmentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SkillAssessmentCategory::class, 'skill_assessment_category_id');
    }

    /** @return HasMany<SkillAssessmentOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(SkillAssessmentOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsToMany<SkillAssessment, $this> */
    public function assessments(): BelongsToMany
    {
        return $this->belongsToMany(SkillAssessment::class, 'skill_assessment_question_links')->withPivot(['weight', 'sort_order']);
    }
}
