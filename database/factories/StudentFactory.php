<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\StudentSource;
use App\Models\Student;
use App\Models\User;
use App\Support\Numbering\NumberGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => fn () => User::factory()->create()->assignRole(Role::Student->value)->id,
            'student_number' => fn () => app(NumberGenerator::class)->next('student'),
            'source' => StudentSource::Admin,
        ];
    }
}
