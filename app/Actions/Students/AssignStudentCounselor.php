<?php

namespace App\Actions\Students;

use App\Enums\Role;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * REQ §10 "Assign Counselor" and §26 "Counselor assignments must be
 * tracked": closes the current assignment and opens a new one.
 */
class AssignStudentCounselor
{
    public function __invoke(Student $student, ?User $counselor, ?User $actor, ?string $note = null, bool $notify = true): void
    {
        if ($counselor && (! $counselor->hasRole(Role::Counselor->value) || ! $counselor->isActive())) {
            throw ValidationException::withMessages(['counselor_id' => __('Students can only be assigned to active counselors.')]);
        }

        if ($student->counselor_id === $counselor?->id) {
            return;
        }

        DB::transaction(function () use ($student, $counselor, $actor, $note) {
            $student->counselorAssignments()->whereNull('ended_at')->update(['ended_at' => now()]);

            $student->counselor_id = $counselor?->id;
            $student->save();

            $student->counselorAssignments()->create([
                'counselor_id' => $counselor?->id,
                'assigned_by' => $actor?->id,
                'note' => $note,
                'assigned_at' => now(),
            ]);

            activity('students')
                ->performedOn($student)
                ->causedBy($actor)
                ->event('counselor_assigned')
                ->withProperties(['counselor' => $counselor?->name])
                ->log('counselor_assigned');
        });

        if ($notify && $counselor && $counselor->isNot($actor)) {
            $counselor->notify(new StudentAssigned($student));
        }
    }
}
