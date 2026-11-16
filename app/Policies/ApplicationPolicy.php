<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\Student;
use App\Models\User;

/**
 * REQ §13 and §27: counselors manage applications for their own students;
 * students see their own; recording a decision needs its own permission.
 */
class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('applications.view');
    }

    public function view(User $user, Application $application): bool
    {
        return StudentPolicy::isOwner($user, $application->student)
            || ($user->can('applications.view') && StudentPolicy::inScope($user, $application->student));
    }

    public function create(User $user, Student $student): bool
    {
        return $user->can('applications.update') && StudentPolicy::inScope($user, $student);
    }

    public function update(User $user, Application $application): bool
    {
        return $user->can('applications.update') && StudentPolicy::inScope($user, $application->student);
    }

    public function decide(User $user, Application $application): bool
    {
        return $user->can('applications.decide') && StudentPolicy::inScope($user, $application->student);
    }
}
