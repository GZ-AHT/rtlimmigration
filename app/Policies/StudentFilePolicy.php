<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\StudentFile;
use App\Models\User;

/** REQ §11.2 file opening; §27 rule 3–4 (counselors: permitted students; students: own files). */
class StudentFilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('files.view');
    }

    public function view(User $user, StudentFile $file): bool
    {
        return StudentPolicy::isOwner($user, $file->student)
            || ($user->can('files.view') && StudentPolicy::inScope($user, $file->student));
    }

    public function open(User $user, Student $student): bool
    {
        return $user->can('files.manage') && StudentPolicy::inScope($user, $student);
    }

    public function update(User $user, StudentFile $file): bool
    {
        return $user->can('files.manage') && StudentPolicy::inScope($user, $file->student);
    }
}
