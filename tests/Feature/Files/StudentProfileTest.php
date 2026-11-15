<?php

use App\Enums\Role;
use App\Models\Counselor;
use App\Models\Student;
use App\Models\User;
use App\Support\StudentRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function profileCounselor(): User
{
    $u = User::factory()->create()->assignRole(Role::Counselor->value);
    Counselor::create(['user_id' => $u->id]);

    return $u;
}

function profilePayload(array $overrides = []): array
{
    return array_merge([
        'phone' => '+880 1711-000000',
        'date_of_birth' => '2003-05-10',
        'gender' => 'female',
        'nationality' => 'Bangladeshi',
        'address_line' => 'House 1, Road 2',
        'city' => 'Dhaka',
        'country' => 'Bangladesh',
        'emergency_contact_name' => 'Parent',
        'emergency_contact_relation' => 'Mother',
        'emergency_contact_phone' => '01800000000',
        'passport_number' => 'a1234567',
        'passport_issue_date' => '2022-01-01',
        'passport_expiry_date' => '2032-01-01',
        'educations' => [
            ['level' => 'hsc', 'institution' => 'Dhaka College', 'result' => '5.00', 'passing_year' => 2021],
        ],
    ], $overrides);
}

it('lets the assigned counselor save the full profile', function () {
    $c = profileCounselor();
    $student = Student::factory()->create(['counselor_id' => $c->id]);

    $this->actingAs($c)->put(route('counselor.students.profile.update', $student), profilePayload())
        ->assertRedirect();

    $student->refresh()->load('profile', 'educations');

    expect($student->phone)->toBe('+880 1711-000000')
        ->and($student->profile->passport_number)->toBe('A1234567')
        ->and($student->educations)->toHaveCount(1)
        ->and(StudentRecords::completeness($student)['percent'])->toBe(100);
});

it('encrypts the passport number and never logs it', function () {
    $student = Student::factory()->create();

    $this->actingAs($this->userWithRole(Role::SuperAdmin))->put(route('admin.students.profile.update', $student), profilePayload());

    $raw = DB::table('student_profiles')->where('student_id', $student->id)->value('passport_number');
    expect($raw)->not->toContain('A1234567');

    $logged = Activity::where('log_name', 'students')->get()->pluck('attribute_changes')->toJson();
    expect($logged)->not->toContain('A1234567');
});

it('updates, adds and removes education rows', function () {
    $student = Student::factory()->create();
    $admin = $this->userWithRole(Role::SuperAdmin);
    $this->actingAs($admin)->put(route('admin.students.profile.update', $student), profilePayload());
    $existing = $student->educations()->first();

    $this->actingAs($admin)->put(route('admin.students.profile.update', $student), profilePayload(['educations' => [
        ['id' => $existing->id, 'level' => 'hsc', 'institution' => 'Renamed College'],
        ['level' => 'bachelor', 'institution' => 'University of Dhaka'],
    ]]));

    expect($student->educations()->pluck('institution')->all())->toBe(['Renamed College', 'University of Dhaka']);

    $this->actingAs($admin)->put(route('admin.students.profile.update', $student), profilePayload(['educations' => []]));
    expect($student->educations()->count())->toBe(0);
});

it('validates passport and dates', function () {
    $student = Student::factory()->create();

    $this->actingAs($this->userWithRole(Role::SuperAdmin))->put(route('admin.students.profile.update', $student), profilePayload([
        'passport_number' => 'AB-12',
        'passport_expiry_date' => '2021-01-01',
        'date_of_birth' => now()->addDay()->toDateString(),
        'educations' => [['level' => 'hsc', 'institution' => '']],
    ]))->assertSessionHasErrors(['passport_number', 'passport_expiry_date', 'date_of_birth', 'educations.0.institution']);
});

it("stops counselors editing other counselors' students", function () {
    $student = Student::factory()->create(['counselor_id' => profileCounselor()->id]);

    $this->actingAs(profileCounselor())->put(route('counselor.students.profile.update', $student), profilePayload())->assertForbidden();
});

it('lets students edit their own profile in the portal', function () {
    $student = Student::factory()->create();

    $this->actingAs($student->user)->get(route('portal.profile.edit'))->assertOk();
    $this->actingAs($student->user)->put(route('portal.profile.update'), profilePayload())->assertRedirect(route('portal.profile.edit'));

    expect($student->profile()->first()->city)->toBe('Dhaka');
});

it('masks the passport number on the staff summary', function () {
    $student = Student::factory()->create();
    $admin = $this->userWithRole(Role::SuperAdmin);
    $this->actingAs($admin)->put(route('admin.students.profile.update', $student), profilePayload());

    $this->actingAs($admin)->get(route('admin.students.show', ['student' => $student, 'tab' => 'profile']))
        ->assertInertia(fn ($p) => $p->where('profile.passport.0.value', '•••••567'));
});
