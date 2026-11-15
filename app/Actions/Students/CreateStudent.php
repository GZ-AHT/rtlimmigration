<?php

namespace App\Actions\Students;

use App\Enums\Role;
use App\Enums\StudentSource;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Support\Numbering\NumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a student: the user account (student role) plus the central
 * student record with a unique student number (REQ §11.2). Staff-created
 * accounts get a secure invitation to set their own password.
 */
class CreateStudent
{
    public function __construct(private NumberGenerator $numbers, private AssignStudentCounselor $assignCounselor) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, counselor_id?: int|null, lead_id?: int|null, send_invitation?: bool}  $data
     * @param  User|null  $existing  attach to this account instead of creating one
     */
    public function __invoke(array $data, StudentSource $source, ?User $actor = null, ?User $existing = null): Student
    {
        $student = DB::transaction(function () use ($data, $source, $actor, $existing) {
            $user = $existing ?? User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::password(40),
            ]);

            if (! $user->hasRole(Role::Student->value)) {
                $user->assignRole(Role::Student->value);
            }

            $student = Student::create([
                'user_id' => $user->id,
                'student_number' => $this->numbers->next('student'),
                'phone' => $data['phone'] ?? null,
                'lead_id' => $data['lead_id'] ?? null,
                'source' => $source,
                'created_by' => $actor?->id,
            ]);

            if (! empty($data['counselor_id'])) {
                $counselor = User::findOrFail($data['counselor_id']);
                ($this->assignCounselor)($student, $counselor, $actor, notify: false);
            }

            return $student;
        });

        $isNewAccount = $existing === null;

        if ($isNewAccount && $source !== StudentSource::SelfRegistration && ($data['send_invitation'] ?? true)) {
            $student->user->notify(new AccountInvitation($actor?->name));
        }

        return $student;
    }
}
