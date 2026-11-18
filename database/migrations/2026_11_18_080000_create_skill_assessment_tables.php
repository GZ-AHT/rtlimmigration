<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Country-based skill migration assessments: one assessment per destination
 * country, a shared question bank (a question can be used by several
 * countries), recommendation bands, and visitor submissions that keep a
 * frozen copy of the questions and scoring taken when they started.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_assessment_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('skill_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->unique()->constrained()->restrictOnDelete();
            $table->string('slug', 60)->unique();
            $table->string('title', 160);
            $table->string('summary', 300)->nullable();
            $table->text('intro')->nullable();
            $table->unsignedTinyInteger('pass_percentage')->default(60);
            $table->unsignedTinyInteger('minutes')->nullable();
            // draft | published
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Recommendations: the band with the highest min_percent the score reaches applies.
        Schema::create('skill_assessment_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_assessment_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('min_percent');
            $table->string('title', 120);
            $table->text('text');
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('skill_assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_assessment_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('text', 300);
            $table->string('help', 300)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('skill_assessment_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_assessment_question_id')->constrained()->cascadeOnDelete();
            $table->string('label', 200);
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
        });

        // Which questions each country's assessment uses, in what order and with what weight.
        Schema::create('skill_assessment_question_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_assessment_id')->constrained()->cascadeOnDelete();
            // Explicit name: the generated one is longer than MySQL's 64-character limit.
            $table->foreignId('skill_assessment_question_id')->constrained(indexName: 'sa_question_links_question_fk')->cascadeOnDelete();
            $table->unsignedTinyInteger('weight')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['skill_assessment_id', 'skill_assessment_question_id'], 'skill_assessment_question_unique');
        });

        Schema::create('skill_assessment_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('skill_assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('country_name', 80);
            $table->string('assessment_title', 160);
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email');
            $table->string('phone', 32);
            // started | completed
            $table->string('status', 20)->default('started');
            // An editor previewing an unpublished assessment: no lead is created.
            $table->boolean('is_preview')->default(false);
            // Questions, options (with points), weights, passing % and bands, frozen at start.
            $table->json('snapshot');
            $table->json('answers')->nullable();
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->json('category_scores')->nullable();
            $table->string('band_title', 120)->nullable();
            $table->text('band_text')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['country_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_assessment_submissions');
        Schema::dropIfExists('skill_assessment_question_links');
        Schema::dropIfExists('skill_assessment_options');
        Schema::dropIfExists('skill_assessment_questions');
        Schema::dropIfExists('skill_assessment_bands');
        Schema::dropIfExists('skill_assessments');
        Schema::dropIfExists('skill_assessment_categories');
    }
};
