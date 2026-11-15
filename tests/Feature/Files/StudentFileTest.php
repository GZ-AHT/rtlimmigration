<?php

use App\Enums\FileStatus;
use App\Enums\Role;
use App\Events\StudentFileOpened;
use App\Models\Counselor;
use App\Models\Student;
use App\Models\StudentFile;
use App\Models\User;
use App\Notifications\StudentFileOpenedNotification;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

function fileCounselor(): User
{
    $u = User::factory()->create()->assignRole(Role::Counselor->value);
    Counselor::create(['user_id' => $u->id]);

    return $u;
}

it('opens a numbered file, fires the event and tells the student', function () {
    Event::fake([StudentFileOpened::class]);
    Notification::fake();
    $c = fileCounselor();
    $student = Student::factory()->create(['counselor_id' => $c->id]);

    $this->actingAs($c)->post(route('counselor.students.files.store', $student), ['intake' => 'September 2027'])->assertRedirect();

    $file = StudentFile::sole();
    expect($file->file_number)->toStartWith('FILE-')->and($file->status)->toBe(FileStatus::Open)->and($file->opened_by)->toBe($c->id);
    Event::assertDispatched(StudentFileOpened::class);
    Notification::assertSentTo($student->user, StudentFileOpenedNotification::class);
});

it('allows several files per student', function () {
    $student = Student::factory()->create();
    $admin = $this->userWithRole(Role::SuperAdmin);

    $this->actingAs($admin)->post(route('admin.students.files.store', $student), []);
    $this->actingAs($admin)->post(route('admin.students.files.store', $student), []);

    expect($student->files()->count())->toBe(2)
        ->and($student->files()->pluck('file_number')->unique())->toHaveCount(2);
});

it("blocks counselors from other counselors' students and files", function () {
    $student = Student::factory()->create(['counselor_id' => fileCounselor()->id]);
    $file = $student->files()->create(['file_number' => 'FILE-X', 'opened_at' => now()]);
    $other = fileCounselor();

    $this->actingAs($other)->post(route('counselor.students.files.store', $student), [])->assertForbidden();
    $this->actingAs($other)->get(route('counselor.files.show', $file))->assertForbidden();
    $this->actingAs($other)->get(route('counselor.files.index'))->assertInertia(fn ($p) => $p->where('files.total', 0));
});

it('requires a reason to close a file and reopens cleanly', function () {
    $student = Student::factory()->create();
    $admin = $this->userWithRole(Role::SuperAdmin);
    $file = $student->files()->create(['file_number' => 'FILE-Y', 'opened_at' => now()]);

    $this->actingAs($admin)->patch(route('admin.files.status', $file), ['status' => 'closed'])->assertSessionHasErrors('close_reason');

    $this->actingAs($admin)->patch(route('admin.files.status', $file), ['status' => 'closed', 'close_reason' => 'Client withdrew']);
    expect($file->fresh()->status)->toBe(FileStatus::Closed)->and($file->fresh()->closed_at)->not->toBeNull();

    $this->actingAs($admin)->patch(route('admin.files.status', $file), ['status' => 'open']);
    expect($file->fresh()->closed_at)->toBeNull()->and($file->fresh()->close_reason)->toBeNull();
});

it('shows students their own files in the portal', function () {
    $student = Student::factory()->create();
    $student->files()->create(['file_number' => 'FILE-Z', 'opened_at' => now()]);
    Student::factory()->create()->files()->create(['file_number' => 'FILE-OTHER', 'opened_at' => now()]);

    $this->actingAs($student->user)->get(route('portal.file'))
        ->assertInertia(fn ($p) => $p->component('portal/File')->has('files', 1)->where('files.0.file_number', 'FILE-Z'));
});
