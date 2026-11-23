<?php

use App\Enums\Role;
use App\Models\Country;
use App\Models\Ticket;
use App\Models\University;
use App\Models\VisaType;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

it('deletes a ticket with its replies', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $ticket = Ticket::create([
        'ticket_number' => 'TKT-DEL-1', 'subject' => 'Gone', 'description' => 'x',
        'priority' => 'low', 'status' => 'open', 'requester_name' => 'V', 'requester_email' => 'v@example.com',
    ]);

    $this->actingAs($admin)->delete(route('admin.tickets.destroy', $ticket))->assertRedirect(route('admin.tickets.index'));

    expect(Ticket::find($ticket->id))->toBeNull();
});

it('deletes an unused country but keeps one with universities', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $free = Country::create(['name' => 'Freeland', 'iso2' => 'FL']);

    $this->actingAs($admin)->delete(route('admin.countries.destroy', $free))->assertRedirect();
    expect(Country::find($free->id))->toBeNull();
});

it('refuses to delete a visa type that has applications', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $type = VisaType::first();

    if ($type->applications()->exists()) {
        $this->actingAs($admin)->delete(route('admin.visa-types.destroy', $type))->assertSessionHasErrors('visa_type');
    } else {
        $this->actingAs($admin)->delete(route('admin.visa-types.destroy', $type))->assertRedirect();
        expect(VisaType::find($type->id))->toBeNull();
    }
});

it('deletes several universities at once', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $country = Country::create(['name' => 'Bulkland', 'iso2' => 'BL']);
    $ids = collect(['Alpha', 'Beta', 'Gamma'])->map(fn ($n) => University::create([
        'name' => $n.' University', 'slug' => strtolower($n), 'country_id' => $country->id,
    ])->id);

    $this->actingAs($admin)->delete(route('admin.universities.bulk-destroy'), ['ids' => $ids->take(2)->all()])->assertRedirect();

    expect(University::whereKey($ids)->count())->toBe(1);
    $this->actingAs($admin)->delete(route('admin.universities.bulk-destroy'), ['ids' => []])->assertSessionHasErrors('ids');
});
