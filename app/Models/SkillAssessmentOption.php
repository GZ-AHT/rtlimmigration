<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $skill_assessment_question_id
 * @property string $label
 * @property int $points
 * @property int $sort_order
 */
#[Fillable(['skill_assessment_question_id', 'label', 'points', 'sort_order'])]
class SkillAssessmentOption extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['points' => 'integer', 'sort_order' => 'integer'];
    }
}
