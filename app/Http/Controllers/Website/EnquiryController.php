<?php

namespace App\Http\Controllers\Website;

use App\Actions\Leads\CaptureLead;
use App\Enums\ContactChannel;
use App\Enums\LeadSource;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Service;
use App\Models\User;
use App\Notifications\NewLeadReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public consultation enquiry (REQ §7 "Website Lead Form", §11 step 1).
 * Fields follow the Figma Contact page form. The full Figma page is built
 * in Phase 9; this delivers a working form so real leads flow in now.
 */
class EnquiryController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('website/Contact', [
            'services' => Service::active()->get(['id', 'name'])->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->name])->values(),
            'countries' => Country::destinations()->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            'channels' => array_values(array_filter(ContactChannel::options(), fn ($o) => in_array($o['value'], ['phone', 'email', 'whatsapp', 'video', 'in_person'], true))),
        ]);
    }

    public function store(Request $request, CaptureLead $capture): RedirectResponse
    {
        // Honeypot: real visitors never see or fill this field.
        if ($request->filled('website')) {
            return back();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+()\-\s]{6,32}$/'],
            'service_id' => ['required', 'integer', Rule::exists(Service::class, 'id')->where('is_active', true)],
            'country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')->where('is_destination', true)],
            'contact_preference' => ['nullable', Rule::in(['phone', 'email', 'whatsapp', 'video', 'in_person'])],
            'message' => ['required', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ], [
            'phone.regex' => __('Enter a valid phone number, including the country code.'),
            'consent.accepted' => __('Please agree to be contacted so we can respond to your enquiry.'),
        ]);

        $lead = $capture([
            ...$data,
            'consent' => true,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], LeadSource::Website);

        $lead->load('service');

        Notification::send(
            User::permission('leads.assign')->where('status', UserStatus::Active->value)->get()
                ->merge(User::role('super-admin')->where('status', UserStatus::Active->value)->get())
                ->unique('id'),
            new NewLeadReceived($lead),
        );

        $this->toast(['type' => 'success', 'message' => __('Thank you! Your enquiry has been sent. Our team will be in touch soon.')]);

        return back();
    }
}
