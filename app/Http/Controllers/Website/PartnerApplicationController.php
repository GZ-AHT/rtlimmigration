<?php

namespace App\Http\Controllers\Website;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\User;
use App\Notifications\PartnerApplicationReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Website partner application form (REQ §16, Figma "Partner application").
 * Fields follow the owner's reference "RTL Bangladesh — Partner
 * application.png". Creates a pending application with optional supporting
 * documents, which are stored privately; reviewers approve or reject it in
 * the admin panel.
 */
class PartnerApplicationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Honeypot: real visitors never see or fill this field.
        if ($request->filled('company_url')) {
            return back();
        }

        $data = $request->validate([
            'organisation' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(array_keys(Partner::TYPES))],
            'country_id' => ['required', 'integer', Rule::exists(Country::class, 'id')->where('is_active', true)],
            'city' => ['required', 'string', 'max:120'],
            'website' => ['nullable', 'url:https,http', 'max:255'],
            'contact_name' => ['required', 'string', 'max:120'],
            'job_title' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+()\-\s]{6,32}$/'],
            'partnership_interest' => ['required', Rule::in(array_keys(PartnerApplication::INTERESTS))],
            'years_in_operation' => ['nullable', Rule::in(array_keys(PartnerApplication::YEARS))],
            'destinations' => ['required', 'array', 'min:1', 'max:20'],
            'destinations.*' => ['integer', 'distinct', Rule::exists(Country::class, 'id')->where('is_destination', true)],
            'message' => ['required', 'string', 'max:3000'],
            'documents' => ['nullable', 'array', 'max:3'],
            'documents.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png'],
            'consent' => ['accepted'],
        ], [
            'phone.regex' => __('Enter a valid phone number, including the country code.'),
            'consent.accepted' => __('Please confirm the details are correct and that we may contact you.'),
            'destinations.required' => __('Choose at least one study destination.'),
            'documents.*.mimes' => __('Documents must be PDF, JPG or PNG files.'),
            'documents.*.max' => __('Each document must be 10 MB or smaller.'),
        ]);

        // One open application per email address, so a resubmit doesn't duplicate it.
        $existing = PartnerApplication::query()->where('email', $data['email'])->where('status', 'pending')->exists();

        if (! $existing) {
            $disk = (string) config('documents.disk');

            $application = DB::transaction(function () use ($request, $data, $disk) {
                $application = PartnerApplication::create([
                    ...Arr::except($data, ['documents', 'consent']),
                    'destinations' => array_values(array_map('intval', $data['destinations'])),
                    'status' => 'pending',
                    'ip_address' => $request->ip(),
                ]);

                foreach ($request->file('documents', []) as $file) {
                    $path = $file->storeAs('partner-documents/applications/'.$application->id, Str::random(40).'.'.$file->extension(), $disk);
                    $application->documents()->create([
                        'title' => Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 120, ''),
                        'disk' => $disk,
                        'path' => $path,
                        'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
                        'mime_type' => (string) $file->getMimeType(),
                        'size' => (int) $file->getSize(),
                    ]);
                }

                return $application;
            });

            Notification::send(
                // Super Admins hold every permission through a gate, not as stored rows.
                User::permission('partners.approve')->where('status', UserStatus::Active->value)->get()
                    ->merge(User::role(Role::SuperAdmin->value)->where('status', UserStatus::Active->value)->get())
                    ->unique('id'),
                new PartnerApplicationReceived($application),
            );
        }

        $this->toast(['type' => 'success', 'message' => __('Thank you. We have received your application and will be in touch soon.')]);

        return back();
    }
}
