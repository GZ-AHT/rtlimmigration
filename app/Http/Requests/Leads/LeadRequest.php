<?php

namespace App\Http\Requests\Leads;

use App\Enums\ContactChannel;
use App\Enums\LeadSource;
use App\Models\Country;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Staff create / edit form for a lead (REQ §7). */
class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+()\-\s]{6,32}$/', 'required_without:email'],
            'country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
            'service_id' => ['nullable', 'integer', Rule::exists(Service::class, 'id')],
            'contact_preference' => ['nullable', Rule::enum(ContactChannel::class)],
            'message' => ['nullable', 'string', 'max:2000'],
            // Website leads only come in through the public form.
            'source' => [$this->isMethod('post') ? 'required' : 'sometimes', Rule::enum(LeadSource::class)->except([LeadSource::Website])],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => __('Enter an email address or a phone number.'),
            'phone.required_without' => __('Enter a phone number or an email address.'),
            'phone.regex' => __('Enter a valid phone number.'),
        ];
    }
}
