<?php

use App\Enums\CounselorAvailability;
use App\Enums\Role;
use App\Models\Counselor;
use App\Notifications\AccountInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('adds a counselor with a profile and an invitation', function () {
    Notification::fake();

    $this->actingAs($this->userWithRole(Role::SuperAdmin))->post(route('admin.counselors.store'), [
        'name' => 'Nadia Islam', 'email' => 'nadia@example.com', 'designation' => 'Senior', 'availability' => 'available', 'max_active_leads' => 25,
    ])->assertRedirect();

    $counselor = Counselor::sole();
    expect($counselor->user->hasRole('counselor'))->toBeTrue()->and($counselor->max_active_leads)->toBe(25);
    Notification::assertSentTo($counselor->user, AccountInvitation::class);
});

it('creates missing profiles for users given the role elsewhere', function () {
    $this->userWithRole(Role::Counselor);

    $this->actingAs($this->userWithRole(Role::SuperAdmin))->get(route('admin.counselors.index'))
        ->assertInertia(fn ($p) => $p->has('counselors', 1));
});

it('shows performance and updates availability', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $user = $this->userWithRole(Role::Counselor);
    $counselor = Counselor::create(['user_id' => $user->id]);

    $this->actingAs($admin)->get(route('admin.counselors.show', $counselor))
        ->assertInertia(fn ($p) => $p->component('admin/counselors/Show')->has('performance', 4));

    $this->actingAs($admin)->put(route('admin.counselors.update', $counselor), [
        'name' => $user->name, 'email' => $user->email, 'availability' => 'on_leave',
    ])->assertRedirect();

    expect($counselor->fresh()->availability)->toBe(CounselorAvailability::OnLeave);
});

it('lets lead managers view counselors but not add them', function () {
    $manager = $this->userWithRole(Role::LeadManager);

    $this->actingAs($manager)->get(route('admin.counselors.index'))->assertOk();
    $this->actingAs($manager)->get(route('admin.counselors.create'))->assertForbidden();
});
