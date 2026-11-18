<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A recommendation for scores at or above min_percent.
 *
 * @property int $id
 * @property int $skill_assessment_id
 * @property int $min_percent
 * @property string $title
 * @property string $text
 * @property int $sort_order
 */
#[Fillable(['skill_assessment_id', 'min_percent', 'title', 'text', 'sort_order'])]
class SkillAssessmentBand extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['min_percent' => 'integer', 'sort_order' => 'integer'];
    }
}
