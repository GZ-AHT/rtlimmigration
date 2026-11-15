<?php

namespace App\Actions\Students;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Saves personal, contact, passport and education details in one
 * transaction (REQ §10, §11.2). Changes are audit-logged by the models.
 */
class UpdateStudentProfile
{
    /**
     * @param  array<string, mixed>  $profile  student_profiles columns
     * @param  list<array<string, mixed>>  $educations  full replacement list
     */
    public function __invoke(Student $student, ?string $phone, array $profile, array $educations): void
    {
        DB::transaction(function () use ($student, $phone, $profile, $educations) {
            $student->update(['phone' => $phone]);
            $student->profile()->updateOrCreate([], $profile);

            $keep = [];

            foreach ($educations as $i => $row) {
                $attributes = [...$row, 'sort_order' => $i];
                unset($attributes['id']);

                $education = isset($row['id'])
                    ? $student->educations()->whereKey($row['id'])->first()
                    : null;

                $education = $education
                    ? tap($education)->update($attributes)
                    : $student->educations()->create($attributes);

                $keep[] = $education->id;
            }

            $student->educations()->whereNotIn('id', $keep)->delete();

            activity('students')->performedOn($student)->event('profile_updated')->log('profile_updated');
        });
    }
}
