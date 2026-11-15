<?php

namespace App\Models;

use App\Enums\EducationLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $student_id
 * @property EducationLevel $level
 * @property string $institution
 * @property string|null $board_or_university
 * @property string|null $country
 * @property string|null $major
 * @property string|null $result
 * @property int|null $passing_year
 * @property int $sort_order
 */
#[Fillable(['student_id', 'level', 'institution', 'board_or_university', 'country', 'major', 'result', 'passing_year', 'sort_order'])]
class StudentEducation extends Model
{
    // "education" is uncountable in Laravel's inflector; name the table explicitly.
    protected $table = 'student_educations';

    protected function casts(): array
    {
        return ['level' => EducationLevel::class, 'passing_year' => 'integer'];
    }
}
