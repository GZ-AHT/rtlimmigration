<?php

namespace App\Http\Requests\Students;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Student profile form, used by staff and by the student in the portal (REQ §10, §10.1). */
class StudentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $phone = ['nullable', 'string', 'max:32', 'regex:/^[0-9+()\-\s]{6,32}$/'];
        $text = fn (int $max) => ['nullable', 'string', "max:{$max}"];

        return [
            'phone' => $phone,
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'nationality' => $text(80),
            'place_of_birth' => $text(120),
            'alternate_phone' => $phone,
            'address_line' => $text(255),
            'city' => $text(80),
            'state' => $text(80),
            'postal_code' => $text(20),
            'country' => $text(80),
            'emergency_contact_name' => $text(120),
            'emergency_contact_relation' => $text(60),
            'emergency_contact_phone' => $phone,
            'passport_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]{5,20}$/'],
            'passport_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'passport_expiry_date' => ['nullable', 'date', 'after:passport_issue_date'],
            'passport_issue_place' => $text(120),
            'passport_issuing_country' => $text(80),
            'educations' => ['present', 'array', 'max:12'],
            'educations.*.id' => ['nullable', 'integer'],
            'educations.*.level' => ['required', Rule::enum(EducationLevel::class)],
            'educations.*.institution' => ['required', 'string', 'max:255'],
            'educations.*.board_or_university' => $text(255),
            'educations.*.country' => $text(80),
            'educations.*.major' => $text(255),
            'educations.*.result' => $text(40),
            'educations.*.passing_year' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 6)],
        ];
    }

    public function messages(): array
    {
        return [
            'passport_number.regex' => __('Passport numbers contain only letters and digits.'),
            'phone.regex' => __('Enter a valid phone number.'),
            'alternate_phone.regex' => __('Enter a valid phone number.'),
            'emergency_contact_phone.regex' => __('Enter a valid phone number.'),
            'educations.*.institution.required' => __('Enter the institution name.'),
        ];
    }

    /** @return array<string, mixed> */
    public function profile(): array
    {
        $data = $this->safe()->except(['phone', 'educations']);
        $data['passport_number'] = isset($data['passport_number']) ? strtoupper((string) $data['passport_number']) : null;

        return $data;
    }

    /** @return list<array<string, mixed>> */
    public function educations(): array
    {
        /** @var list<array<string, mixed>> */
        return array_values($this->validated('educations', []));
    }
}
