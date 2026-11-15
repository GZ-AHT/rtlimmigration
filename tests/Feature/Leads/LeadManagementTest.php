<?php

use App\Actions\Leads\CaptureLead;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\Counselor;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Notifications\FollowUpDue;
use App\Notifications\LeadAssigned;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

function makeLead(array $data = [], ?User $counselor = null): Lead
{
    $lead = app(CaptureLead::class)(array_merge(['name' => 'Test Lead', 'email' => fake()->unique()->safeEmail(), 'phone' => null], $data), LeadSource::Manual);

    if ($counselor) {
        $lead->update(['counselor_id' => $counselor->id, 'status' => LeadStatus::Assigned]);
    }

    return $lead->fresh();
}

function counselor(): User
{
    $user = User::factory()->create()->assignRole(Role::Counselor->value);
    Counselor::create(['user_id' => $user->id]);

    return $user;
}

describe('visibility', function () {
    it('shows lead managers every lead and counselors only their own', function () {
        $mine = counselor();
        $other = counselor();
        makeLead(['name' => 'Mine'], $mine);
        makeLead(['name' => 'Theirs'], $other);
        makeLead(['name' => 'Unassigned']);

        $this->actingAs($this->userWithRole(Role::LeadManager))->get(route('admin.leads.index'))
            ->assertInertia(fn ($page) => $page->where('leads.total', 3));

        $this->actingAs($mine)->get(route('counselor.leads.index'))
            ->assertInertia(fn ($page) => $page->where('leads.total', 1)->where('leads.data.0.name', 'Mine'));
    });

    it("blocks counselors from other counselors' leads, even by URL", function () {
        $mine = counselor();
        $theirs = makeLead([], counselor());

        $this->actingAs($mine)->get(route('counselor.leads.show', $theirs))->assertForbidden();
        $this->actingAs($mine)->post(route('counselor.leads.notes.store', $theirs), ['body' => 'x'])->assertForbidden();
        $this->actingAs($mine)->post(route('counselor.leads.convert', $theirs))->assertForbidden();
    });

    it('does not give counselors create, assign or delete actions', function () {
        $mine = counselor();
        $lead = makeLead([], $mine);

        $this->actingAs($mine)->get('/counselor/leads/create')->assertNotFound();
        $this->actingAs($mine)->post(route('admin.leads.assign', $lead), ['counselor_id' => $mine->id])->assertForbidden();
    });

    it('filters by unassigned, phone digits and website inquiries', function () {
        $manager = $this->userWithRole(Role::LeadManager);
        makeLead(['name' => 'Phone Person', 'phone' => '+880 1711-000111']);
        makeLead(['name' => 'Assigned'], counselor());
        app(CaptureLead::class)(['name' => 'Web Visitor', 'email' => 'web@example.com'], LeadSource::Website);

        $this->actingAs($manager)->get(route('admin.leads.index', ['counselor' => 'unassigned']))
            ->assertInertia(fn ($page) => $page->where('leads.total', 2));
        $this->actingAs($manager)->get(route('admin.leads.index', ['search' => '1711000']))
            ->assertInertia(fn ($page) => $page->where('leads.total', 1)->where('leads.data.0.name', 'Phone Person'));
        $this->actingAs($manager)->get(route('admin.inquiries.index'))
            ->assertInertia(fn ($page) => $page->where('leads.total', 1)->where('leads.data.0.name', 'Web Visitor'));
    });
});

describe('creating and editing', function () {
    it('lets a lead manager add a lead and assign it in one step', function () {
        Notification::fake();
        $manager = $this->userWithRole(Role::LeadManager);
        $c = counselor();

        $this->actingAs($manager)->post(route('admin.leads.store'), [
            'name' => 'Walk In', 'phone' => '01712345678', 'source' => 'walk_in', 'counselor_id' => $c->id,
        ])->assertRedirect();

        $lead = Lead::sole();
        expect($lead->source)->toBe(LeadSource::WalkIn)->and($lead->counselor_id)->toBe($c->id)->and($lead->status)->toBe(LeadStatus::Assigned);
        Notification::assertSentTo($c, LeadAssigned::class);
    });

    it('requires an email or a phone, and refuses the website source for staff', function () {
        $manager = $this->userWithRole(Role::LeadManager);

        $this->actingAs($manager)->post(route('admin.leads.store'), ['name' => 'X', 'source' => 'manual'])
            ->assertSessionHasErrors(['email', 'phone']);
        $this->actingAs($manager)->post(route('admin.leads.store'), ['name' => 'X', 'email' => 'x@example.com', 'source' => 'website'])
            ->assertSessionHasErrors('source');
    });

    it('lets the assigned counselor edit details, which re-checks duplicates', function () {
        $c = counselor();
        $original = makeLead(['email' => 'same@example.com']);
        $lead = makeLead(['email' => 'unique@example.com'], $c);

        $this->actingAs($c)->put(route('counselor.leads.update', $lead), ['name' => 'Renamed', 'email' => 'SAME@example.com'])
            ->assertRedirect(route('counselor.leads.show', $lead));

        expect($lead->fresh()->name)->toBe('Renamed')->and($lead->fresh()->duplicate_of_id)->toBe($original->id);
    });
});

describe('assignment', function () {
    it('assigns, transfers and records the history', function () {
        Notification::fake();
        $manager = $this->userWithRole(Role::LeadManager);
        [$a, $b] = [counselor(), counselor()];
        $lead = makeLead();

        $this->actingAs($manager)->post(route('admin.leads.assign', $lead), ['counselor_id' => $a->id])->assertRedirect();
        $this->actingAs($manager)->post(route('admin.leads.assign', $lead), ['counselor_id' => $b->id, 'note' => 'Rebalancing'])->assertRedirect();

        expect($lead->fresh()->counselor_id)->toBe($b->id)
            ->and($lead->assignments()->pluck('type')->all())->toBe(['transferred', 'assigned']);
        Notification::assertSentTo($b, LeadAssigned::class, fn ($n) => $n->transfer === true);
    });

    it('needs leads.transfer to move a lead between counselors', function () {
        $role = Spatie\Permission\Models\Role::findOrCreate('assigner', 'web');
        $role->givePermissionTo(['area.admin', 'leads.view', 'leads.assign']);
        $assigner = User::factory()->create()->assignRole('assigner');
        $lead = makeLead([], counselor());

        $this->actingAs($assigner)->post(route('admin.leads.assign', $lead), ['counselor_id' => counselor()->id])->assertForbidden();
    });

    it('only assigns to active counselors', function () {
        $manager = $this->userWithRole(Role::LeadManager);
        $lead = makeLead();

        $this->actingAs($manager)->post(route('admin.leads.assign', $lead), ['counselor_id' => $this->userWithRole(Role::Accounts)->id])
            ->assertSessionHasErrors('counselor_id');
    });
});

describe('working a lead', function () {
    it('moves to Contacted when the first contact is logged', function () {
        $c = counselor();
        $lead = makeLead([], $c);

        $this->actingAs($c)->post(route('counselor.leads.contacts.store', $lead), ['channel' => 'phone', 'summary' => 'Explained the process'])->assertRedirect();

        expect($lead->fresh()->status)->toBe(LeadStatus::Contacted)
            ->and($lead->fresh()->last_contacted_at)->not->toBeNull()
            ->and($lead->contacts()->count())->toBe(1);
    });

    it('changes status by hand but never to Converted or Assigned', function () {
        $c = counselor();
        $lead = makeLead([], $c);

        $this->actingAs($c)->patch(route('counselor.leads.status', $lead), ['status' => 'interested'])->assertRedirect();
        expect($lead->fresh()->status)->toBe(LeadStatus::Interested);

        $this->actingAs($c)->patch(route('counselor.leads.status', $lead), ['status' => 'converted'])->assertSessionHasErrors('status');
        $this->actingAs($c)->patch(route('counselor.leads.status', $lead), ['status' => 'assigned'])->assertSessionHasErrors('status');
    });

    it('schedules and completes follow-ups, keeping the next date in sync', function () {
        $c = counselor();
        $lead = makeLead([], $c);

        $this->actingAs($c)->post(route('counselor.leads.follow-ups.store', $lead), ['due_at' => now()->addDays(3)->toDateTimeString(), 'note' => 'Later']);
        $this->actingAs($c)->post(route('counselor.leads.follow-ups.store', $lead), ['due_at' => now()->addDay()->toDateTimeString(), 'note' => 'Sooner']);

        $lead->refresh();
        expect($lead->status)->toBe(LeadStatus::FollowUp)
            ->and($lead->next_follow_up_at->isSameDay(now()->addDay()))->toBeTrue();

        $sooner = $lead->followUps()->where('note', 'Sooner')->first();
        $this->actingAs($c)->patch(route('counselor.leads.follow-ups.complete', [$lead, $sooner]), ['outcome' => 'Called'])->assertRedirect();

        expect($lead->fresh()->next_follow_up_at->isSameDay(now()->addDays(3)))->toBeTrue();
    });

    it('rejects follow-ups in the past', function () {
        $c = counselor();
        $lead = makeLead([], $c);

        $this->actingAs($c)->post(route('counselor.leads.follow-ups.store', $lead), ['due_at' => now()->subHour()->toDateTimeString()])
            ->assertSessionHasErrors('due_at');
    });

    it('reminds the counselor once when a follow-up falls due', function () {
        Notification::fake();
        $c = counselor();
        $lead = makeLead([], $c);
        LeadFollowUp::create(['lead_id' => $lead->id, 'user_id' => $c->id, 'due_at' => now()->subMinute()]);
        LeadFollowUp::create(['lead_id' => $lead->id, 'user_id' => $c->id, 'due_at' => now()->addDay()]);

        $this->artisan('leads:remind-follow-ups')->assertSuccessful();
        $this->artisan('leads:remind-follow-ups')->assertSuccessful();

        Notification::assertSentToTimes($c, FollowUpDue::class, 1);
    });

    it('lists open follow-ups for the counselor', function () {
        $c = counselor();
        $lead = makeLead([], $c);
        LeadFollowUp::create(['lead_id' => $lead->id, 'user_id' => $c->id, 'due_at' => now()->subHour()]);
        LeadFollowUp::create(['lead_id' => makeLead([], counselor())->id, 'due_at' => now()->subHour()]);

        $this->actingAs($c)->get(route('counselor.follow-ups.index', ['when' => 'overdue']))
            ->assertInertia(fn ($page) => $page->where('followUps.total', 1));
    });
});

describe('conversion', function () {
    it('converts a lead into a student account with an invitation', function () {
        Notification::fake();
        $c = counselor();
        $lead = makeLead(['name' => 'Ayesha', 'email' => 'ayesha@example.com', 'phone' => '0171'], $c);

        $this->actingAs($c)->post(route('counselor.leads.convert', $lead))->assertRedirect();

        $student = Student::sole();
        $lead->refresh();

        expect($lead->status)->toBe(LeadStatus::Converted)
            ->and($lead->student_id)->toBe($student->id)
            ->and($student->counselor_id)->toBe($c->id)
            ->and($student->student_number)->toStartWith('STU-')
            ->and($student->user->hasRole('student'))->toBeTrue();

        Notification::assertSentTo($student->user, AccountInvitation::class);
    });

    it('needs an email, refuses staff emails and cannot convert twice', function () {
        $manager = $this->userWithRole(Role::LeadManager);

        $noEmail = makeLead(['email' => null, 'phone' => '01700000000']);
        $this->actingAs($manager)->post(route('admin.leads.convert', $noEmail))->assertSessionHasErrors('lead');

        $staff = makeLead(['email' => $this->userWithRole(Role::Accounts)->email]);
        $this->actingAs($manager)->post(route('admin.leads.convert', $staff))->assertSessionHasErrors('lead');

        $lead = makeLead(['email' => 'once@example.com']);
        $this->actingAs($manager)->post(route('admin.leads.convert', $lead));
        $this->actingAs($manager)->post(route('admin.leads.convert', $lead))->assertSessionHasErrors('lead');
        expect(Student::count())->toBe(1);
    });

    it('makes converted leads read-only', function () {
        $manager = $this->userWithRole(Role::LeadManager);
        $lead = makeLead(['email' => 'done@example.com']);
        $this->actingAs($manager)->post(route('admin.leads.convert', $lead));

        $this->actingAs($manager)->put(route('admin.leads.update', $lead), ['name' => 'Changed', 'email' => 'done@example.com'])->assertForbidden();
        $this->actingAs($manager)->patch(route('admin.leads.status', $lead), ['status' => 'rejected'])->assertSessionHasErrors('status');
    });
});

it('lets only lead.delete holders delete, softly', function () {
    $lead = makeLead();

    $this->actingAs($this->userWithRole(Role::LeadManager))->delete(route('admin.leads.destroy', $lead))->assertForbidden();
    $this->actingAs($this->userWithRole(Role::SuperAdmin))->delete(route('admin.leads.destroy', $lead))->assertRedirect(route('admin.leads.index'));

    expect(Lead::withTrashed()->find($lead->id)->trashed())->toBeTrue();
});
