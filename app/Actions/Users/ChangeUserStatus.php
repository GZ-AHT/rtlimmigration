<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeUserStatus
{
    public function __invoke(User $user, UserStatus $status, User $actor): void
    {
        if (! UserPolicy::canChangeAccess($actor, $user)) {
            throw ValidationException::withMessages(['status' => __('You cannot change the status of your own account.')]);
        }

        if ($status === UserStatus::Inactive && UserPolicy::isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages(['status' => __('This is the last active Super Admin and cannot be deactivated.')]);
        }

        DB::transaction(function () use ($user, $status) {
            $user->status = $status;
            $user->save();

            if ($status === UserStatus::Inactive) {
                // Sign the user out everywhere (database session driver).
                DB::table('sessions')->where('user_id', $user->getKey())->delete();
                $user->forceFill(['remember_token' => null])->saveQuietly();
            }
        });
    }
}
