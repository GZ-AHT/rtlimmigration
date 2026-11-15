<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a user (REQ §6 "Delete User"). Records that reference the
 * user (payments, files, audit history) stay intact.
 */
class DeleteUser
{
    public function __invoke(User $user, User $actor): void
    {
        if (! UserPolicy::canChangeAccess($actor, $user)) {
            throw ValidationException::withMessages(['user' => __('You cannot delete your own account here.')]);
        }

        if (UserPolicy::isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages(['user' => __('This is the last active Super Admin and cannot be deleted.')]);
        }

        DB::transaction(function () use ($user) {
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            $user->delete();
        });
    }
}
