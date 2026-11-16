<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 6 (REQ §13, §14, §17; roadmap Q10 and Q14): one application
     * engine for every visa type. A visa type (study, working, visitor,
     * work & examination…) has its own requirements, document checklist,
     * fee and workflow; a workflow is an ordered list of stages that Super
     * Admin can edit.
     */
    public function up(): void
    {
        Schema::create('visa_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // study, work, visitor, exam, other: decides which menus list it.
            $table->string('kind', 16)->default('other')->index();
            $table->string('description', 500)->nullable();
            // REQ §17 "Visa Fees": the invoice a counselor can raise in one click.
            $table->foreignId('payment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('fee_amount')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // REQ §17 "Visa Requirements": what the applicant must meet.
        Schema::create('visa_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_type_id')->constrained()->cascadeOnDelete();
            // Null applies to every destination.
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('description', 1000)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // REQ §17 "Required Documents": the checklist for this visa type.
        Schema::create('document_category_visa_type', function (Blueprint $table) {
            $table->foreignId('visa_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['visa_type_id', 'document_category_id']);
        });

        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Both null = the default workflow. The most specific match wins:
            // type + country, then type, then country, then default.
            $table->foreignId('visa_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['visa_type_id', 'country_id']);
        });

        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // standard, decision, visit_requested, visit_completed, final
            $table->string('kind', 20)->default('standard');
            // Shown to the student on their timeline.
            $table->string('description', 500)->nullable();
            $table->boolean('notify_student')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            // Stages already used in history are retired, never removed.
            $table->softDeletes();

            $table->index(['workflow_id', 'sort_order']);
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number', 32)->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_file_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visa_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workflow_id')->constrained()->restrictOnDelete();
            $table->foreignId('current_stage_id')->nullable()->constrained('workflow_stages')->nullOnDelete();
            $table->string('status', 16)->default('in_progress')->index(); // in_progress, approved, rejected, completed, withdrawn
            // Programme, job title or examination name.
            $table->string('title')->nullable();
            // University, employer or test centre.
            $table->string('organisation')->nullable();
            $table->string('reference_number', 120)->nullable();
            // Interview or examination date.
            $table->timestamp('scheduled_at')->nullable();
            // Examination score or outcome.
            $table->string('result')->nullable();
            $table->string('decision', 16)->nullable(); // approved, rejected
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamp('office_visit_requested_at')->nullable();
            $table->timestamp('office_visit_at')->nullable();
            $table->timestamp('office_visit_completed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });

        // REQ §13.3: previous and new status, who, when, remarks, whether
        // the student was notified. Stage names are copied so the record
        // still reads correctly after a workflow is edited.
        Schema::create('application_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('workflow_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->nullable()->constrained('workflow_stages')->nullOnDelete();
            $table->string('from_stage_name')->nullable();
            $table->string('to_stage_name');
            $table->string('event', 20)->default('stage'); // created, stage, decision, withdrawn
            $table->string('note', 2000)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('student_notified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // REQ §13.3 "Supporting Documents": decision letters and other
        // files attached to an application or to one status update.
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('application_id')->nullable()->after('student_file_id')->constrained()->nullOnDelete();
            $table->foreignId('application_status_history_id')->nullable()->after('application_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('application_status_history_id');
            $table->dropConstrainedForeignId('application_id');
        });

        Schema::dropIfExists('application_status_histories');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('workflow_stages');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('document_category_visa_type');
        Schema::dropIfExists('visa_requirements');
        Schema::dropIfExists('visa_types');
    }
};
