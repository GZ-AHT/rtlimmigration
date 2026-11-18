<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of skill migration questions (Age, English, Work experience…),
 * shown as a step in the assessment and scored separately in the result.
 *
 * @property int $id
 * @property string $name
 * @property int $sort_order
 */
#[Fillable(['name', 'sort_order'])]
class SkillAssessmentCategory extends Model
{
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** @return HasMany<SkillAssessmentQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(SkillAssessmentQuestion::class);
    }
}
