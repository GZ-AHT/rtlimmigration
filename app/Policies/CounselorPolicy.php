<?php

namespace App\Policies;

use App\Models\Counselor;
use App\Models\User;

class CounselorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('counselors.view');
    }

    public function view(User $user, Counselor $counselor): bool
    {
        return $user->can('counselors.view');
    }

    public function create(User $user): bool
    {
        return $user->can('counselors.manage');
    }

    public function update(User $user, Counselor $counselor): bool
    {
        return $user->can('counselors.manage');
    }
}
