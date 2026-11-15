<?php

namespace App\Actions\Leads;

use App\Actions\Students\CreateStudent;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\StudentSource;
use App\Models\Lead;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * REQ §7 "Convert Lead to Student" / §11 step 4: creates the client's
 * account (with a secure invitation) or links an existing student account
 * with the same email, then marks the lead Converted.
 */
class ConvertLeadToStudent
{
    public function __construct(private CreateStudent $createStudent) {}

    public function __invoke(Lead $lead, User $actor, bool $sendInvitation = true): Student
    {
        if ($lead->status === LeadStatus::Converted && $lead->student_id) {
            throw ValidationException::withMessages(['lead' => __('This lead has already been converted.')]);
        }

        if (! $lead->email) {
            throw ValidationException::withMessages(['lead' => __('Add an email address to the lead before converting it. The student needs it to sign in.')]);
        }

        return DB::transaction(function () use ($lead, $actor, $sendInvitation) {
            $existing = User::withTrashed()->where('email', $lead->email)->first();

            if ($existing && ! $existing->hasRole(Role::Student->value)) {
                throw ValidationException::withMessages(['lead' => __('This email belongs to a staff account and cannot become a student.')]);
            }

            $student = ($existing ? $existing->student : null) ?? $this->createStudent->__invoke([
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'counselor_id' => $lead->counselor_id,
                'lead_id' => $lead->id,
                'send_invitation' => $sendInvitation,
            ], StudentSource::LeadConversion, $actor, $existing);

            $lead->update([
                'status' => LeadStatus::Converted,
                'student_id' => $student->id,
                'converted_at' => now(),
            ]);

            return $student;
        });
    }
}
