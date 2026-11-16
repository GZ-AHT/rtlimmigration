<?php

use App\Actions\Applications\ApplicationWorkflow;
use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\Counselor;
use App\Models\Country;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\PaymentRequest;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\User;
use App\Models\VisaType;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use App\Notifications\ApplicationUpdated;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    Storage::fake('local');

    $this->counselor = User::factory()->create()->assignRole(Role::Counselor->value);
    Counselor::create(['user_id' => $this->counselor->id]);
    $this->student = Student::factory()->create(['counselor_id' => $this->counselor->id]);
    $this->admin = $this->userWithRole(Role::SuperAdmin);
    $this->study = VisaType::where('slug', 'study-visa')->first();
    $this->work = VisaType::where('slug', 'working-visa')->first();
    $this->exam = VisaType::where('slug', 'work-examination')->first();
    $this->canada = Country::where('iso2', 'CA')->first();
});

/** Start an application as the counselor and return it. */
function startApplication($test, ?VisaType $type = null, array $overrides = []): Application
{
    $test->actingAs($test->counselor)->post(route('counselor.applications.store'), array_merge([
        'student_id' => $test->student->id,
        'visa_type_id' => ($type ?? $test->study)->id,
        'country_id' => $test->canada->id,
        'organisation' => 'University of Toronto',
    ], $overrides))->assertSessionHasNoErrors();

    return Application::latest('id')->firstOrFail();
}

function stageOf(Application $application, string $kindOrName): WorkflowStage
{
    return $application->workflow->stages->first(fn (WorkflowStage $s) => $s->kind->value === $kindOrName || $s->name === $kindOrName)
        ?? throw new RuntimeException("No stage [{$kindOrName}]");
}

function advanceTo($test, Application $application, WorkflowStage $stage, array $data = [], ?User $as = null)
{
    return $test->actingAs($as ?? $test->counselor)->post(route('counselor.applications.advance', $application), ['stage_id' => $stage->id, ...$data]);
}

function letter(): UploadedFile
{
    return UploadedFile::fake()->create('decision.pdf', 80, 'application/pdf');
}

describe('starting an application', function () {
    it('numbers it, puts it on the first stage of the right workflow and tells the student', function () {
        Notification::fake();

        $application = startApplication($this);

        expect($application->application_number)->toStartWith('APP-')
            ->and($application->status)->toBe(ApplicationStatus::InProgress)
            ->and($application->workflow->name)->toBe('Standard visa process')
            ->and($application->stage->name)->toBe('File Opened')
            ->and($application->history()->count())->toBe(1)
            ->and($application->history()->first()->student_notified_at)->not->toBeNull();

        Notification::assertSentTo($this->student->user, ApplicationUpdated::class);
        Notification::assertNotSentTo($this->counselor, ApplicationUpdated::class);
    });

    it('gives each visa type its own workflow in one engine', function () {
        $work = startApplication($this, $this->work);
        $exam = startApplication($this, $this->exam);

        expect($work->workflow->name)->toBe('Working visa process')
            ->and($exam->workflow->name)->toBe('Work & examination process')
            ->and($exam->stage->name)->toBe('Registered')
            ->and(Application::count())->toBe(2);
    });

    it('prefers a workflow for the visa type and country over the type alone', function () {
        $specific = Workflow::create(['name' => 'Canada study', 'visa_type_id' => $this->study->id, 'country_id' => $this->canada->id]);
        $specific->stages()->create(['name' => 'Biometrics first', 'sort_order' => 10]);
        $typeOnly = Workflow::create(['name' => 'Any study', 'visa_type_id' => $this->study->id]);
        $typeOnly->stages()->create(['name' => 'Start', 'sort_order' => 10]);

        expect(startApplication($this)->workflow->name)->toBe('Canada study')
            ->and(startApplication($this, null, ['country_id' => Country::where('iso2', 'AU')->value('id')])->workflow->name)->toBe('Any study')
            ->and(startApplication($this, VisaType::where('slug', 'visitor-visa')->first())->workflow->name)->toBe('Standard visa process');
    });

    it("stops counselors starting applications for other counselors' students", function () {
        $other = Student::factory()->create();

        $this->actingAs($this->counselor)->post(route('counselor.applications.store'), ['student_id' => $other->id, 'visa_type_id' => $this->study->id])->assertForbidden();
        expect(Application::count())->toBe(0);
    });
});

describe('updating the stage', function () {
    it('records previous and new stage, who, remarks, documents and that the student was told', function () {
        $application = startApplication($this);
        Notification::fake();

        advanceTo($this, $application, stageOf($application, 'Documents Submitted'), ['note' => 'All originals received.', 'files' => [letter()]])->assertSessionHasNoErrors();

        $history = $application->history()->first();
        expect($application->fresh()->stage->name)->toBe('Documents Submitted')
            ->and($history->from_stage_name)->toBe('File Opened')
            ->and($history->to_stage_name)->toBe('Documents Submitted')
            ->and($history->changed_by)->toBe($this->counselor->id)
            ->and($history->note)->toBe('All originals received.')
            ->and($history->student_notified_at)->not->toBeNull()
            ->and($history->documents()->count())->toBe(1);

        $document = Document::sole();
        expect($document->application_id)->toBe($application->id)->and($document->student_id)->toBe($this->student->id);
        Storage::disk('local')->assertExists($document->path);
        Notification::assertSentTo($this->student->user, ApplicationUpdated::class);
    });

    it('respects a stage that is set not to notify the student', function () {
        $application = startApplication($this);
        $stage = stageOf($application, 'Document Verification');
        $stage->update(['notify_student' => false]);
        Notification::fake();

        advanceTo($this, $application, $stage)->assertSessionHasNoErrors();

        expect($application->history()->first()->student_notified_at)->toBeNull();
        Notification::assertNothingSent();
    });

    it('lets staff post an update on the current stage, but not an empty one', function () {
        $application = startApplication($this);
        $current = $application->stage;

        advanceTo($this, $application, $current)->assertSessionHasErrors('note');
        advanceTo($this, $application, $current, ['note' => 'Chased the university by email.'])->assertSessionHasNoErrors();

        expect($application->history()->first()->event)->toBe('update')->and($application->fresh()->current_stage_id)->toBe($current->id);
    });

    it('refuses a stage from another workflow', function () {
        $application = startApplication($this);
        $foreign = Workflow::where('name', 'Working visa process')->first()->stages->first();

        advanceTo($this, $application, $foreign)->assertSessionHasErrors('stage_id');
    });

    it('is closed to other counselors and to the student', function () {
        $application = startApplication($this);
        $stage = stageOf($application, 'Documents Submitted');
        $other = User::factory()->create()->assignRole(Role::Counselor->value);

        advanceTo($this, $application, $stage, [], $other)->assertForbidden();
        advanceTo($this, $application, $stage, [], $this->student->user)->assertForbidden();
    });
});

describe('the decision', function () {
    it('needs an outcome and the decision document', function () {
        $application = startApplication($this);
        $decision = stageOf($application, 'decision');

        advanceTo($this, $application, $decision)->assertSessionHasErrors('decision');
        advanceTo($this, $application, $decision, ['decision' => 'approved'])->assertSessionHasErrors('files');

        expect($application->fresh()->decision)->toBeNull();
    });

    it('records an approval and always tells the student', function () {
        $application = startApplication($this);
        $decision = stageOf($application, 'decision');
        $decision->update(['notify_student' => false]);
        Notification::fake();

        advanceTo($this, $application, $decision, ['decision' => 'approved', 'files' => [letter()]])->assertSessionHasNoErrors();

        $application->refresh();
        expect($application->status)->toBe(ApplicationStatus::Approved)
            ->and($application->decision)->toBe('approved')
            ->and($application->decided_by)->toBe($this->counselor->id)
            ->and($application->history()->first()->event)->toBe('decision');
        Notification::assertSentTo($this->student->user, ApplicationUpdated::class);
    });

    it('needs a reason for a rejection and shows it to the student', function () {
        $application = startApplication($this);
        $decision = stageOf($application, 'decision');

        advanceTo($this, $application, $decision, ['decision' => 'rejected', 'files' => [letter()]])->assertSessionHasErrors('rejection_reason');
        advanceTo($this, $application, $decision, ['decision' => 'rejected', 'rejection_reason' => 'Insufficient proof of funds.', 'files' => [letter()]])->assertSessionHasNoErrors();

        expect($application->fresh()->status)->toBe(ApplicationStatus::Rejected);

        $this->actingAs($this->student->user)->get(route('portal.applications.index'))
            ->assertInertia(fn ($page) => $page->component('portal/Applications')
                ->where('applications.0.decision', 'rejected')
                ->where('applications.0.rejection_reason', 'Insufficient proof of funds.'));
    });

    it('needs the decide permission', function () {
        $application = startApplication($this);
        $this->counselor->roles->first()->revokePermissionTo('applications.decide');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        advanceTo($this, $application, stageOf($application, 'decision'), ['decision' => 'approved', 'files' => [letter()]])->assertForbidden();
        expect($application->fresh()->decision)->toBeNull();
    });

    it('then runs the office visit and completes the case', function () {
        $application = startApplication($this);

        // Cannot be closed before a decision exists.
        advanceTo($this, $application, stageOf($application, 'final'))->assertSessionHasErrors('stage_id');

        advanceTo($this, $application, stageOf($application, 'decision'), ['decision' => 'approved', 'files' => [letter()]]);
        advanceTo($this, $application, stageOf($application, 'visit_requested'), ['office_visit_at' => now()->addDays(3)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();

        expect($application->fresh()->office_visit_requested_at)->not->toBeNull()->and($application->fresh()->office_visit_at)->not->toBeNull();

        advanceTo($this, $application, stageOf($application, 'visit_completed'))->assertSessionHasNoErrors();
        advanceTo($this, $application, stageOf($application, 'final'))->assertSessionHasNoErrors();

        $application->refresh();
        expect($application->office_visit_completed_at)->not->toBeNull()
            ->and($application->status)->toBe(ApplicationStatus::Completed)
            ->and($application->completed_at)->not->toBeNull();

        // A completed case is locked.
        advanceTo($this, $application, stageOf($application, 'Visa Processing'))->assertSessionHasErrors('stage_id');
    });
});

it('tracks an examination through its own workflow with a result and no visa decision', function () {
    $application = startApplication($this, $this->exam, ['title' => 'IELTS Academic', 'organisation' => 'British Council Dhaka']);

    advanceTo($this, $application, stageOf($application, 'Result Received'), ['result' => 'Overall 7.0'])->assertSessionHasNoErrors();
    advanceTo($this, $application, stageOf($application, 'final'))->assertSessionHasNoErrors();

    expect($application->fresh()->result)->toBe('Overall 7.0')
        ->and($application->fresh()->status)->toBe(ApplicationStatus::Completed)
        ->and($application->fresh()->decision)->toBeNull();
});

it('can be withdrawn with a reason, which locks it', function () {
    $application = startApplication($this);

    $this->actingAs($this->counselor)->patch(route('counselor.applications.withdraw', $application), [])->assertSessionHasErrors('reason');
    $this->actingAs($this->counselor)->patch(route('counselor.applications.withdraw', $application), ['reason' => 'Student changed plans.'])->assertSessionHasNoErrors();

    expect($application->fresh()->status)->toBe(ApplicationStatus::Withdrawn);
    advanceTo($this, $application, stageOf($application, 'Documents Submitted'))->assertSessionHasErrors('stage_id');
});

it('raises the visa type fee as an ordinary invoice', function () {
    $application = startApplication($this, $this->work);

    $this->actingAs($this->counselor)->post(route('counselor.applications.fee', $application))->assertSessionHasErrors('fee');

    $this->work->update(['payment_type_id' => PaymentType::where('slug', 'visa-processing-fee')->value('id'), 'fee_amount' => 2500000]);
    $this->actingAs($this->counselor)->post(route('counselor.applications.fee', $application))->assertSessionHasNoErrors();

    $invoice = PaymentRequest::sole();
    expect($invoice->amount)->toBe(2500000)->and($invoice->student_id)->toBe($this->student->id)->and($invoice->description)->toContain($application->application_number);
});

describe('screens', function () {
    it('lists applications by family and scopes counselors to their students', function () {
        startApplication($this);
        startApplication($this, $this->work);
        startApplication($this, $this->exam);
        $other = User::factory()->create()->assignRole(Role::Counselor->value);

        $this->actingAs($this->counselor)->get(route('counselor.applications.index'))
            ->assertInertia(fn ($page) => $page->component('applications/Index')->has('applications.data', 3));
        $this->actingAs($this->counselor)->get(route('counselor.applications.exams'))
            ->assertInertia(fn ($page) => $page->where('title', 'Work & examination')->has('applications.data', 1));
        $this->actingAs($this->admin)->get(route('admin.applications.working'))
            ->assertInertia(fn ($page) => $page->where('title', 'Working visa')->has('applications.data', 1));
        $this->actingAs($this->counselor)->get(route('counselor.applications.updates'))
            ->assertInertia(fn ($page) => $page->where('activeOnly', true)->has('applications.data', 3));
        $this->actingAs($other)->get(route('counselor.applications.index'))
            ->assertInertia(fn ($page) => $page->has('applications.data', 0));
        $this->actingAs($other)->get(route('counselor.applications.show', Application::first()))->assertForbidden();
    });

    it('shows the application with stages, checklist and permissions', function () {
        $application = startApplication($this);
        $this->study->requirements()->create(['title' => 'IELTS 6.0 or above']);
        $this->study->requirements()->create(['title' => 'Australia only', 'country_id' => Country::where('iso2', 'AU')->value('id')]);

        $this->actingAs($this->counselor)->get(route('counselor.applications.show', $application))
            ->assertInertia(fn ($page) => $page->component('applications/Show')
                ->has('stages', 16)
                ->where('stages.0.state', 'current')
                ->where('stages.1.state', 'upcoming')
                ->has('checklist', 8)
                ->where('checklist.0.status', 'missing')
                ->has('requirements', 1)
                ->where('can.update', true)
                ->where('can.decide', true));
        $this->actingAs($this->counselor)->get(route('counselor.applications.create'))->assertInertia(fn ($page) => $page->component('applications/Form'));
    });

    it('gives the student their own applications and timeline, without staff names', function () {
        $application = startApplication($this);
        advanceTo($this, $application, stageOf($application, 'Documents Submitted'), ['note' => 'Received.', 'files' => [letter()]]);
        $stranger = Student::factory()->create();

        $this->actingAs($this->student->user)->get(route('portal.applications.status'))
            ->assertInertia(fn ($page) => $page->component('portal/ApplicationStatus')
                ->has('applications', 1)
                ->has('applications.0.history', 2)
                ->where('applications.0.history.0.by', null)
                ->has('applications.0.history.0.documents', 1));
        $this->actingAs($stranger->user)->get(route('portal.applications.index'))
            ->assertInertia(fn ($page) => $page->has('applications', 0));

        // The supporting document opens for its owner only.
        $document = Document::sole();
        $this->actingAs($this->student->user)->get(route('portal.documents.show', $document))->assertOk();
        $this->actingAs($stranger->user)->get(route('portal.documents.show', $document))->assertForbidden();
        $this->actingAs($this->student->user)->get(route('portal.dashboard'))->assertInertia(fn ($page) => $page->has('applications', 1));
    });
});

describe('configuration', function () {
    it('is for workflows.manage only', function () {
        $this->actingAs($this->counselor)->get(route('admin.workflows.index'))->assertForbidden();
        $manager = $this->userWithRole(Role::LeadManager);
        $this->actingAs($manager)->get(route('admin.workflows.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.visa-types.index'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.workflows.index'))->assertInertia(fn ($page) => $page->component('admin/visa/Workflows')->has('workflows', 3));
        $this->actingAs($this->admin)->get(route('admin.visa-types.index'))->assertInertia(fn ($page) => $page->component('admin/visa/Types')->has('types', 4));
    });

    it('lets Super Admin add a visa type with its checklist, fee and requirements', function () {
        $this->actingAs($this->admin)->post(route('admin.visa-types.store'), [
            'name' => 'Skilled migration', 'kind' => 'work', 'fee' => '30000',
            'payment_type_id' => PaymentType::first()->id,
            'document_category_ids' => DocumentCategory::limit(2)->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

        $type = VisaType::where('name', 'Skilled migration')->firstOrFail();
        expect($type->fee_amount)->toBe(3000000)->and($type->documentCategories()->count())->toBe(2);

        $this->actingAs($this->admin)->post(route('admin.visa-types.requirements.store', $type), ['title' => 'Skills assessment'])->assertSessionHasNoErrors();
        expect($type->requirements()->count())->toBe(1);

        // A fee needs a fee type to be invoiced as.
        $this->actingAs($this->admin)->put(route('admin.visa-types.update', $type), ['name' => 'Skilled migration', 'kind' => 'work', 'fee' => '100'])->assertSessionHasErrors('payment_type_id');

        // New types fall back to the default workflow until given their own.
        expect(startApplication($this, $type)->workflow->name)->toBe('Standard visa process');
    });

    it('lets Super Admin build a workflow and edit its stages', function () {
        $this->actingAs($this->admin)->post(route('admin.workflows.store'), ['name' => 'UK visitor', 'visa_type_id' => VisaType::where('slug', 'visitor-visa')->value('id')])->assertSessionHasNoErrors();
        $workflow = Workflow::where('name', 'UK visitor')->firstOrFail();
        expect($workflow->stages()->count())->toBe(16);

        // No two workflows for the same combination; nothing but the default may cover everything.
        $this->actingAs($this->admin)->post(route('admin.workflows.store'), ['name' => 'Dup', 'visa_type_id' => $workflow->visa_type_id])->assertSessionHasErrors('visa_type_id');
        $this->actingAs($this->admin)->post(route('admin.workflows.store'), ['name' => 'Everything'])->assertSessionHasErrors('visa_type_id');

        $this->actingAs($this->admin)->post(route('admin.workflows.stages.store', $workflow), ['name' => 'Biometrics', 'kind' => 'standard'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.workflows.stages.store', $workflow), ['name' => 'Second decision', 'kind' => 'decision'])->assertSessionHasErrors('kind');

        $order = $workflow->stages()->pluck('id')->reverse()->values()->all();
        $this->actingAs($this->admin)->put(route('admin.workflows.stages.reorder', $workflow), ['order' => $order])->assertSessionHasNoErrors();
        expect($workflow->stages()->first()->name)->toBe('Biometrics');
    });

    it('protects stages and workflows that applications are using', function () {
        $application = startApplication($this);
        $workflow = $application->workflow;

        $this->actingAs($this->admin)->delete(route('admin.workflows.stages.destroy', [$workflow, $application->stage]))->assertSessionHasErrors('stage');
        $this->actingAs($this->admin)->delete(route('admin.workflows.destroy', $workflow))->assertSessionHasErrors('workflow');

        // Retiring an unused stage keeps history readable.
        $unused = stageOf($application, 'Interview Scheduled');
        $this->actingAs($this->admin)->delete(route('admin.workflows.stages.destroy', [$workflow, $unused]))->assertSessionHasNoErrors();
        expect(WorkflowStage::withTrashed()->find($unused->id)->trashed())->toBeTrue()->and($workflow->stages()->count())->toBe(15);

        $used = Workflow::where('name', 'Working visa process')->first();
        startApplication($this, $this->work);
        $this->actingAs($this->admin)->delete(route('admin.workflows.destroy', $used))->assertSessionHasErrors('workflow');
    });
});

it('resolves nothing when no workflow has stages', function () {
    WorkflowStage::query()->forceDelete();

    expect(Workflow::resolve($this->study->id, null))->toBeNull();
    expect(fn () => app(ApplicationWorkflow::class)->start($this->student, $this->study, null, null, [], $this->counselor))
        ->toThrow(ValidationException::class);
});
