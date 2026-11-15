<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * REQ §8 counselors, §10 / §11.2 students, §26 counselor assignment
     * tracking. Counselor and student records extend a user account; the
     * personal, passport and education profile arrives with Phase 3.
     */
    public function up(): void
    {
        Schema::create('counselors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phone', 32)->nullable();
            $table->string('designation')->nullable();
            $table->string('availability', 16)->default('available')->index();
            $table->unsignedInteger('max_active_leads')->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('student_number', 32)->unique();
            // Current counselor (a user with the counselor role).
            $table->foreignId('counselor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('lead_id')->nullable()->index();
            $table->string('phone', 32)->nullable();
            $table->string('source', 24);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Full history of who was responsible for a student and when.
        Schema::create('student_counselor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counselor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();

            $table->index(['student_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_counselor_assignments');
        Schema::dropIfExists('students');
        Schema::dropIfExists('counselors');
    }
};
