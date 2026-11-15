<?php

namespace App\Support\Presenters;

use App\Models\Student;
use App\Models\StudentCounselorAssignment;
use App\Models\User;

class StudentPresenter
{
    /** @return array<string, mixed> */
    public static function row(Student $student): array
    {
        return [
            'id' => $student->id,
            'student_number' => $student->student_number,
            'name' => $student->user->name,
            'email' => $student->user->email,
            'phone' => $student->phone,
            'counselor' => $student->counselor?->name,
            'source' => $student->source->label(),
            'account_status' => $student->user->trashed() ? 'deleted' : $student->user->status->value,
            'email_verified' => $student->user->email_verified_at !== null,
            'created_at' => $student->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Student $student, User $actor): array
    {
        return [
            ...self::row($student),
            'user_id' => $student->user_id,
            'counselor_id' => $student->counselor_id,
            'last_login_at' => $student->user->last_login_at?->toIso8601String(),
            'lead' => $student->lead ? [
                'id' => $student->lead->id,
                'number' => $student->lead->lead_number,
                'visible' => $actor->can('view', $student->lead),
            ] : null,
            'creator' => $student->creator?->name,
            'assignments' => $student->counselorAssignments->map(fn (StudentCounselorAssignment $a) => [
                'id' => $a->id,
                'counselor' => $a->counselor?->name,
                'assigned_by' => $a->assigner?->name,
                'note' => $a->note,
                'assigned_at' => $a->assigned_at->toIso8601String(),
                'ended_at' => $a->ended_at?->toIso8601String(),
            ])->values(),
            'can' => [
                'assign_counselor' => $actor->can('assignCounselor', $student),
                'view_user' => $actor->can('view', $student->user),
            ],
        ];
    }
}
