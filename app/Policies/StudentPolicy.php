<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

/** REQ §8 / §27 rule 3: counselors manage only permitted students. */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('students.view');
    }

    public function view(User $user, Student $student): bool
    {
        return $user->can('students.view') && $this->inScope($user, $student);
    }

    public function create(User $user): bool
    {
        return $user->can('students.manage');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can('students.manage') && $this->inScope($user, $student);
    }

    public function assignCounselor(User $user, Student $student): bool
    {
        return $user->can('students.assign-counselor');
    }

    /**
     * The student's own profile: staff with students.manage in scope, or
     * the student themself (REQ §10.1 "Edit personal and passport information").
     */
    public function updateProfile(User $user, Student $student): bool
    {
        return self::isOwner($user, $student) || ($user->can('students.manage') && self::inScope($user, $student));
    }

    public function addNote(User $user, Student $student): bool
    {
        return $user->can('students.manage') && self::inScope($user, $student);
    }

    public static function inScope(User $user, Student $student): bool
    {
        return $user->can('students.assign-counselor') || $student->counselor_id === $user->id;
    }

    public static function isOwner(User $user, Student $student): bool
    {
        return $student->user_id === $user->id;
    }
}
