<?php

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Models\Counselor;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Student;
use App\Models\User;
use App\Notifications\DocumentReviewed;
use App\Notifications\DocumentUploaded;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    Storage::fake('local');
    $this->passport = DocumentCategory::where('slug', 'passport')->first();
});

function docCounselor(): User
{
    $u = User::factory()->create()->assignRole(Role::Counselor->value);
    Counselor::create(['user_id' => $u->id]);

    return $u;
}

function pdf(string $name = 'passport.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

it('stores uploads privately under a random name', function () {
    $student = Student::factory()->create();

    $this->actingAs($student->user)->post(route('portal.documents.store'), ['file' => pdf(), 'category_id' => $this->passport->id])
        ->assertSessionHasNoErrors();

    $doc = Document::sole();
    expect($doc->status)->toBe(DocumentStatus::Pending)
        ->and($doc->original_name)->toBe('passport.pdf')
        ->and($doc->path)->not->toContain('passport')
        ->and($doc->path)->toStartWith('student-documents/'.$student->id.'/');
    Storage::disk('local')->assertExists($doc->path);
});

it('rejects disallowed types, disguised files and oversize uploads', function () {
    $student = Student::factory()->create();

    $this->actingAs($student->user)->post(route('portal.documents.store'), ['file' => UploadedFile::fake()->create('virus.exe', 10), 'category_id' => $this->passport->id])
        ->assertSessionHasErrors('file');
    // A script renamed to .pdf. Uses a real temp file (fake uploads report
    // a MIME type from the extension) so the content sniffing is exercised.
    $path = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($path, "<?php system(\$_GET['c']); ?>\n");
    $disguised = new UploadedFile($path, 'fake.pdf', null, null, true);
    expect($disguised->getMimeType())->not->toBe('application/pdf');
    $this->actingAs($student->user)->post(route('portal.documents.store'), ['file' => $disguised, 'category_id' => $this->passport->id])
        ->assertSessionHasErrors('file');
    $this->actingAs($student->user)->post(route('portal.documents.store'), ['file' => UploadedFile::fake()->create('big.pdf', 20000, 'application/pdf'), 'category_id' => $this->passport->id])
        ->assertSessionHasErrors('file');

    expect(Document::count())->toBe(0);
});

it('needs a name for uncategorised documents', function () {
    $student = Student::factory()->create();

    $this->actingAs($student->user)->post(route('portal.documents.store'), ['file' => pdf()])->assertSessionHasErrors('title');
});

it('keeps the previous version when a document is replaced', function () {
    $student = Student::factory()->create();
    $this->actingAs($student->user)->post(route('portal.documents.store'), ['file' => pdf('v1.pdf'), 'category_id' => $this->passport->id]);
    $this->actingAs($student->user)->post(route('portal.documents.store'), ['file' => pdf('v2.pdf'), 'category_id' => $this->passport->id]);

    [$v1, $v2] = Document::orderBy('id')->get();
    expect($v1->is_current)->toBeFalse()->and($v2->is_current)->toBeTrue()->and($v2->replaces_id)->toBe($v1->id);
});

it('notifies the counselor when a student uploads', function () {
    Notification::fake();
    $c = docCounselor();
    $student = Student::factory()->create(['counselor_id' => $c->id]);

    $this->actingAs($student->user)->post(route('portal.documents.store'), ['file' => pdf(), 'category_id' => $this->passport->id]);

    Notification::assertSentTo($c, DocumentUploaded::class);
});

it('lets the counselor verify or reject with a reason, and tells the student', function () {
    Notification::fake();
    $c = docCounselor();
    $student = Student::factory()->create(['counselor_id' => $c->id]);
    $this->actingAs($c)->post(route('counselor.students.documents.store', $student), ['file' => pdf(), 'category_id' => $this->passport->id]);
    $doc = Document::sole();

    $this->actingAs($c)->patch(route('counselor.documents.review', $doc), ['status' => 'rejected'])->assertSessionHasErrors('rejection_reason');
    $this->actingAs($c)->patch(route('counselor.documents.review', $doc), ['status' => 'rejected', 'rejection_reason' => 'Blurry scan'])->assertRedirect();

    expect($doc->fresh()->status)->toBe(DocumentStatus::Rejected)->and($doc->fresh()->reviewed_by)->toBe($c->id);
    Notification::assertSentTo($student->user, DocumentReviewed::class);

    $this->actingAs($c)->patch(route('counselor.documents.review', $doc), ['status' => 'verified']);
    expect($doc->fresh()->status)->toBe(DocumentStatus::Verified)->and($doc->fresh()->rejection_reason)->toBeNull();
});

it('keeps documents private to the student and their counselor', function () {
    $c = docCounselor();
    $student = Student::factory()->create(['counselor_id' => $c->id]);
    $this->actingAs($student->user)->post(route('portal.documents.store'), ['file' => pdf(), 'category_id' => $this->passport->id]);
    $doc = Document::sole();

    $this->actingAs($student->user)->get(route('portal.documents.show', $doc))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($c)->get(route('counselor.documents.show', $doc))->assertOk();

    $this->actingAs(Student::factory()->create()->user)->get(route('portal.documents.show', $doc))->assertForbidden();
    $this->actingAs(docCounselor())->get(route('counselor.documents.show', $doc))->assertForbidden();
    $this->actingAs(docCounselor())->patch(route('counselor.documents.review', $doc), ['status' => 'verified'])->assertForbidden();
});

it('serves PDFs inline and downloads on request', function () {
    $student = Student::factory()->create();
    $this->actingAs($student->user)->post(route('portal.documents.store'), ['file' => pdf(), 'category_id' => $this->passport->id]);
    $doc = Document::sole();

    expect($this->actingAs($student->user)->get(route('portal.documents.show', $doc))->headers->get('content-disposition'))->toStartWith('inline');
    expect($this->actingAs($student->user)->get(route('portal.documents.show', ['document' => $doc, 'download' => 1]))->headers->get('content-disposition'))->toStartWith('attachment');
});

it('withdraws a pending replacement and restores the previous version', function () {
    $student = Student::factory()->create();
    $this->actingAs($student->user)->post(route('portal.documents.store'), ['file' => pdf('v1.pdf'), 'category_id' => $this->passport->id]);
    $this->actingAs($student->user)->post(route('portal.documents.store'), ['file' => pdf('v2.pdf'), 'category_id' => $this->passport->id]);
    [$v1, $v2] = Document::orderBy('id')->get();

    $this->actingAs($student->user)->delete(route('portal.documents.destroy', $v2))->assertRedirect();

    expect($v1->fresh()->is_current)->toBeTrue()->and(Document::find($v2->id))->toBeNull();
});

it('never deletes reviewed documents, not even as Super Admin', function () {
    $student = Student::factory()->create();
    $admin = $this->userWithRole(Role::SuperAdmin);
    $this->actingAs($admin)->post(route('admin.students.documents.store', $student), ['file' => pdf(), 'category_id' => $this->passport->id]);
    $doc = Document::sole();
    $this->actingAs($admin)->patch(route('admin.documents.review', $doc), ['status' => 'verified']);

    $this->actingAs($admin)->delete(route('admin.documents.destroy', $doc))->assertForbidden();
    $this->actingAs($student->user)->delete(route('portal.documents.destroy', $doc))->assertForbidden();
});

it('lists pending documents for review, oldest first and in scope', function () {
    $c = docCounselor();
    $mine = Student::factory()->create(['counselor_id' => $c->id]);
    $theirs = Student::factory()->create(['counselor_id' => docCounselor()->id]);
    $this->actingAs($mine->user)->post(route('portal.documents.store'), ['file' => pdf(), 'category_id' => $this->passport->id]);
    $this->actingAs($theirs->user)->post(route('portal.documents.store'), ['file' => pdf(), 'category_id' => $this->passport->id]);

    $this->actingAs($c)->get(route('counselor.documents.index'))->assertInertia(fn ($p) => $p->where('documents.total', 1));
});

it('builds the required-document checklist', function () {
    $student = Student::factory()->create();
    $admin = $this->userWithRole(Role::SuperAdmin);
    $this->actingAs($admin)->post(route('admin.students.documents.store', $student), ['file' => pdf(), 'category_id' => $this->passport->id]);
    $this->actingAs($admin)->patch(route('admin.documents.review', Document::sole()), ['status' => 'verified']);

    $this->actingAs($admin)->get(route('admin.students.show', ['student' => $student, 'tab' => 'documents']))
        ->assertInertia(fn ($p) => $p->where('checklist.required', 4)->where('checklist.verified', 1));
});
