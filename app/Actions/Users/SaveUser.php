<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a user from the admin panel, including roles and
 * status, inside one transaction with an audit entry for role changes.
 */
class SaveUser
{
    /**
     * @param  array{name: string, email: string, status: string, roles: list<string>, send_invitation?: bool}  $data
     */
    public function create(array $data, User $actor): User
    {
        $user = DB::transaction(function () use ($data, $actor) {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'status' => $data['status'],
                // Unusable random password until the invitation is accepted.
                'password' => Str::password(40),
            ]);
            $user->save();
            $user->syncRoles($data['roles']);

            $this->logRoles($user, $actor, [], $data['roles']);

            return $user;
        });

        if ($data['send_invitation'] ?? true) {
            $user->notify(new AccountInvitation($actor->name));
        }

        return $user;
    }

    /**
     * @param  array{name: string, email: string, status?: string, roles?: list<string>}  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor) {
            $changesAccess = (isset($data['status']) && $data['status'] !== $user->status->value)
                || (isset($data['roles']) && collect($data['roles'])->sort()->values()->all() !== $user->getRoleNames()->sort()->values()->all());

            if ($changesAccess) {
                $this->guardAccessChange($user, $actor, $data);
            }

            $emailChanged = $data['email'] !== $user->email;

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'status' => $data['status'] ?? $user->status->value,
            ]);

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();

            if (isset($data['roles'])) {
                $before = array_values(array_map('strval', $user->getRoleNames()->all()));
                $user->syncRoles($data['roles']);
                $this->logRoles($user, $actor, $before, $data['roles']);
            }

            if ($emailChanged) {
                $user->sendEmailVerificationNotification();
            }

            return $user;
        });
    }

    /**
     * @param  array{status?: string, roles?: list<string>}  $data
     */
    private function guardAccessChange(User $user, User $actor, array $data): void
    {
        if (! UserPolicy::canChangeAccess($actor, $user)) {
            throw ValidationException::withMessages(['roles' => __('You cannot change the roles or status of your own account.')]);
        }

        $losesSuperAdmin = ($data['status'] ?? null) === UserStatus::Inactive->value
            || (isset($data['roles']) && ! in_array(Role::SuperAdmin->value, $data['roles'], true));

        if ($losesSuperAdmin && UserPolicy::isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages(['roles' => __('This is the last active Super Admin. Assign another Super Admin first.')]);
        }
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    private function logRoles(User $user, User $actor, array $before, array $after): void
    {
        sort($before);
        sort($after);

        if ($before === $after) {
            return;
        }

        activity('users')
            ->performedOn($user)
            ->causedBy($actor)
            ->event('roles_updated')
            ->withProperties(['old' => $before, 'new' => $after])
            ->log('roles_updated');
    }
}
