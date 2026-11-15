<?php

use App\Enums\Role;
use App\Models\Counselor;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

it('lets the assigned counselor add internal notes', function () {
    $c = User::factory()->create()->assignRole(Role::Counselor->value);
    Counselor::create(['user_id' => $c->id]);
    $student = Student::factory()->create(['counselor_id' => $c->id]);

    $this->actingAs($c)->post(route('counselor.students.notes.store', $student), ['body' => 'Prefers evening calls'])->assertRedirect();

    expect($student->notes()->first()->body)->toBe('Prefers evening calls');
});

it('keeps notes away from students', function () {
    $student = Student::factory()->create();

    $this->actingAs($student->user)->post(route('admin.students.notes.store', $student), ['body' => 'x'])->assertForbidden();
});

it('manages document categories, Super Admin only', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);

    $this->actingAs($admin)->post(route('admin.document-categories.store'), ['name' => 'Police clearance', 'is_required' => true, 'is_active' => true])->assertRedirect();
    expect(DocumentCategory::where('slug', 'police-clearance')->first()->is_required)->toBeTrue();

    $this->actingAs($this->userWithRole(Role::LeadManager))->get(route('admin.document-categories.index'))->assertForbidden();
});

it('keeps categories that documents use', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $category = DocumentCategory::first();
    Document::create(['student_id' => Student::factory()->create()->id, 'category_id' => $category->id, 'disk' => 'local', 'path' => 'x', 'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size' => 1]);

    $this->actingAs($admin)->delete(route('admin.document-categories.destroy', $category))->assertSessionHasErrors('category');
});
