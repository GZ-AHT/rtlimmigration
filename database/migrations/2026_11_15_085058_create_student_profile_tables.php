<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * REQ §10 / §11.2: personal, contact, passport and educational
     * information. The passport number is stored encrypted (text column).
     */
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();

            // Personal
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 16)->nullable();
            $table->string('marital_status', 16)->nullable();
            $table->string('nationality', 80)->nullable();
            $table->string('place_of_birth', 120)->nullable();

            // Contact
            $table->string('alternate_phone', 32)->nullable();
            $table->string('address_line')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 80)->nullable();

            // Emergency contact
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_relation', 60)->nullable();
            $table->string('emergency_contact_phone', 32)->nullable();

            // Passport
            $table->text('passport_number')->nullable();
            $table->date('passport_issue_date')->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->string('passport_issue_place', 120)->nullable();
            $table->string('passport_issuing_country', 80)->nullable();

            $table->timestamps();
        });

        Schema::create('student_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('level', 24);
            $table->string('institution');
            $table->string('board_or_university')->nullable();
            $table->string('country', 80)->nullable();
            $table->string('major')->nullable();
            $table->string('result', 40)->nullable();
            $table->unsignedSmallInteger('passing_year')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('student_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_notes');
        Schema::dropIfExists('student_educations');
        Schema::dropIfExists('student_profiles');
    }
};
