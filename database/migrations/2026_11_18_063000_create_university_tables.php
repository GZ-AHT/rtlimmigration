<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ §15 University Management (and the homepage service finder, roadmap Q8). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('universities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('slug', 220)->unique();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('city', 120)->nullable();
            $table->string('address')->nullable();
            $table->string('website')->nullable();
            $table->string('type', 20)->nullable()->comment('public | private');
            $table->unsignedSmallInteger('established_year')->nullable();
            $table->unsignedInteger('ranking')->nullable();
            $table->string('summary', 400)->nullable();
            $table->longText('description')->nullable();
            $table->text('admission_requirements')->nullable();
            $table->text('scholarships')->nullable();
            $table->foreignId('logo_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('banner_id')->nullable()->constrained('media')->nullOnDelete();
            $table->json('gallery')->nullable()->comment('media ids');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['country_id', 'is_active']);
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('slug', 220);
            $table->string('level', 20)->index();
            $table->string('subject', 120)->nullable();
            $table->string('visa_kind', 20)->default('study');
            $table->string('duration', 60)->nullable();
            $table->decimal('tuition_fee', 12, 2)->nullable();
            $table->string('tuition_currency', 3)->nullable();
            $table->string('tuition_period', 20)->nullable()->comment('year | total | semester');
            $table->json('intakes')->nullable()->comment('month numbers 1-12');
            $table->date('application_deadline')->nullable();
            $table->text('requirements')->nullable();
            $table->text('scholarship')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['university_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
        Schema::dropIfExists('universities');
    }
};
