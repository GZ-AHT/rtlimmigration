<?php

use App\Enums\Role;
use App\Enums\StudentSource;
use App\Models\Counselor;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Notifications\StudentAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function aCounselor(): User
{
    $u = User::factory()->create()->assignRole(Role::Counselor->value);
    Counselor::create(['user_id' => $u->id]);

    return $u;
}

it('gives self-registered clients a student record and number', function () {
    $this->post(route('register.store'), ['name' => 'Self Reg', 'email' => 'self@example.com', 'password' => 'password', 'password_confirmation' => 'password']);

    $student = Student::sole();
    expect($student->source)->toBe(StudentSource::SelfRegistration)->and($student->student_number)->toStartWith('STU-');
});

it('lets an admin create a student with a counselor and invitation', function () {
    Notification::fake();
    $c = aCounselor();

    $this->actingAs($this->userWithRole(Role::SuperAdmin))
        ->post(route('admin.students.store'), ['name' => 'New Student', 'email' => 'ns@example.com', 'phone' => '01711111111', 'counselor_id' => $c->id])
        ->assertRedirect();

    $student = Student::sole();
    expect($student->counselor_id)->toBe($c->id)
        ->and($student->counselorAssignments()->count())->toBe(1)
        ->and($student->source)->toBe(StudentSource::Admin);
    Notification::assertSentTo($student->user, AccountInvitation::class);
});

it('assigns students created by a counselor to that counselor', function () {
    $c = aCounselor();

    $this->actingAs($c)->post(route('counselor.students.store'), ['name' => 'Mine', 'email' => 'mine@example.com', 'counselor_id' => aCounselor()->id]);

    expect(Student::sole()->counselor_id)->toBe($c->id)->and(Student::sole()->source)->toBe(StudentSource::Counselor);
});

it('shows counselors only their own students', function () {
    $c = aCounselor();
    $mine = Student::factory()->create(['counselor_id' => $c->id]);
    $theirs = Student::factory()->create(['counselor_id' => aCounselor()->id]);

    $this->actingAs($c)->get(route('counselor.students.index'))->assertInertia(fn ($p) => $p->where('students.total', 1));
    $this->actingAs($c)->get(route('counselor.students.show', $mine))->assertOk();
    $this->actingAs($c)->get(route('counselor.students.show', $theirs))->assertForbidden();
});

it('reassigns counselors and keeps the full history', function () {
    Notification::fake();
    [$a, $b] = [aCounselor(), aCounselor()];
    $student = Student::factory()->create();
    $admin = $this->userWithRole(Role::SuperAdmin);

    $this->actingAs($admin)->patch(route('admin.students.assign', $student), ['counselor_id' => $a->id]);
    $this->actingAs($admin)->patch(route('admin.students.assign', $student), ['counselor_id' => $b->id, 'note' => 'Leave cover']);

    $history = $student->counselorAssignments()->get();
    expect($student->fresh()->counselor_id)->toBe($b->id)
        ->and($history)->toHaveCount(2)
        ->and($history->firstWhere('counselor_id', $a->id)->ended_at)->not->toBeNull()
        ->and($history->firstWhere('counselor_id', $b->id)->ended_at)->toBeNull();
    Notification::assertSentTo($b, StudentAssigned::class);
});

it('only assigns active counselors and only for permitted users', function () {
    $student = Student::factory()->create();

    $this->actingAs($this->userWithRole(Role::SuperAdmin))
        ->patch(route('admin.students.assign', $student), ['counselor_id' => $this->userWithRole(Role::Student)->id])
        ->assertSessionHasErrors('counselor_id');

    $this->actingAs($this->userWithRole(Role::LeadManager))
        ->patch(route('admin.students.assign', $student), ['counselor_id' => aCounselor()->id])
        ->assertForbidden();
});
