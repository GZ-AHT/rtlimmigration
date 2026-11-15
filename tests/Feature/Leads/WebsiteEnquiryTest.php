<?php

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\Lead;
use App\Models\Service;
use App\Notifications\NewLeadReceived;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

function enquiry(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ayesha Rahman',
        'email' => 'ayesha@example.com',
        'phone' => '+880 1806-428212',
        'service_id' => Service::first()->id,
        'message' => 'I would like to study in Canada.',
        'consent' => true,
    ], $overrides);
}

it('shows the enquiry form', function () {
    $this->get(route('contact'))->assertOk()->assertInertia(fn ($page) => $page->component('website/Contact')->has('services', 4));
});

it('turns a website enquiry into a new lead and alerts lead managers', function () {
    Notification::fake();
    $manager = $this->userWithRole(Role::LeadManager);

    $this->post(route('enquiry.store'), enquiry())->assertRedirect();

    $lead = Lead::sole();

    expect($lead->source)->toBe(LeadSource::Website)
        ->and($lead->status)->toBe(LeadStatus::New)
        ->and($lead->lead_number)->toStartWith('LD-')
        ->and($lead->consent_at)->not->toBeNull()
        ->and($lead->phone_normalized)->toBe('1806428212');

    Notification::assertSentTo($manager, NewLeadReceived::class);
});

it('validates the required fields and consent', function () {
    $this->post(route('enquiry.store'), enquiry(['name' => '', 'email' => 'bad', 'consent' => false, 'service_id' => 999]))
        ->assertSessionHasErrors(['name', 'email', 'consent', 'service_id']);

    expect(Lead::count())->toBe(0);
});

it('silently drops submissions that fill the honeypot', function () {
    $this->post(route('enquiry.store'), enquiry(['website' => 'http://spam.example']))->assertRedirect();

    expect(Lead::count())->toBe(0);
});

it('flags repeat enquiries as possible duplicates', function () {
    $this->post(route('enquiry.store'), enquiry());
    $this->post(route('enquiry.store'), enquiry(['email' => 'other@example.com', 'phone' => '01806428212']));

    [$first, $second] = Lead::orderBy('id')->get();

    expect($first->duplicate_of_id)->toBeNull()->and($second->duplicate_of_id)->toBe($first->id);
});

it('rate-limits the public form', function () {
    foreach (range(1, 5) as $i) {
        $this->post(route('enquiry.store'), enquiry(['email' => "p{$i}@example.com"]));
    }

    $this->post(route('enquiry.store'), enquiry(['email' => 'p6@example.com']))->assertTooManyRequests();
});
