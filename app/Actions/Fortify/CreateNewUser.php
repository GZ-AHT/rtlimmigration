<?php

namespace App\Actions\Fortify;

use App\Actions\Students\CreateStudent;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Enums\StudentSource;
use App\Models\User;
use App\Notifications\StudentRegistered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        // Public self-registration is for clients only (REQ §11.2): the
        // account gets the student role and a central student record.
        $user = DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            app(CreateStudent::class)(['name' => $user->name, 'email' => $user->email], StudentSource::SelfRegistration, existing: $user);

            return $user;
        });

        Notification::send(
            User::role(Role::SuperAdmin->value)->where('status', 'active')->get(),
            new StudentRegistered($user),
        );

        return $user;
    }
}
