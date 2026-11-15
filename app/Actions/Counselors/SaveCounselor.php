<?php

namespace App\Actions\Counselors;

use App\Enums\Role;
use App\Models\Counselor;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** REQ §8 "Add New Counselor" / "Edit Counselor". */
class SaveCounselor
{
    /**
     * @param  array{name: string, email: string, phone?: string|null, designation?: string|null, availability: string, max_active_leads?: int|null, bio?: string|null, send_invitation?: bool}  $data
     */
    public function create(array $data, User $actor): Counselor
    {
        $counselor = DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Str::password(40)]);
            $user->assignRole(Role::Counselor->value);

            return Counselor::create(['user_id' => $user->id, ...$this->profile($data)]);
        });

        if ($data['send_invitation'] ?? true) {
            $counselor->user->notify(new AccountInvitation($actor->name));
        }

        return $counselor;
    }

    /**
     * @param  array{name: string, email: string, phone?: string|null, designation?: string|null, availability: string, max_active_leads?: int|null, bio?: string|null}  $data
     */
    public function update(Counselor $counselor, array $data): Counselor
    {
        DB::transaction(function () use ($counselor, $data) {
            $user = $counselor->user;
            $emailChanged = $user->email !== $data['email'];
            $user->fill(['name' => $data['name'], 'email' => $data['email']]);

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();
            $counselor->update($this->profile($data));

            if ($emailChanged) {
                $user->sendEmailVerificationNotification();
            }
        });

        return $counselor;
    }

    /**
     * Ensures a user with the counselor role has a profile row (e.g. one
     * given the role from the Users screen).
     */
    public static function ensureProfile(User $user): Counselor
    {
        return Counselor::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profile(array $data): array
    {
        return [
            'phone' => $data['phone'] ?? null,
            'designation' => $data['designation'] ?? null,
            'availability' => $data['availability'],
            'max_active_leads' => $data['max_active_leads'] ?? null,
            'bio' => $data['bio'] ?? null,
        ];
    }
}
