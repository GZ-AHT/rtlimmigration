<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ §16 Partner Application Management. Partners (agents and
 * institutions) apply on the website; admins approve them into partner
 * profiles with documents, verification, commission and payments. Partners
 * have no login (roadmap Q13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('partner_number', 20)->unique();
            $table->string('organisation', 160);
            $table->string('contact_name', 120);
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city', 120)->nullable();
            $table->string('website')->nullable();
            $table->string('type', 20)->default('agency');
            $table->decimal('commission_rate', 5, 2)->nullable()->comment('percent');
            $table->string('status', 20)->default('active');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_applications', function (Blueprint $table) {
            $table->id();
            $table->string('organisation', 160);
            $table->string('contact_name', 120);
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city', 120)->nullable();
            $table->string('website')->nullable();
            $table->string('type', 20)->default('agency');
            $table->unsignedInteger('students_per_year')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('partner_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('partner_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->string('disk', 20);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('partner_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 200);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('BDT');
            $table->string('status', 20)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('partner_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('BDT');
            $table->string('method', 40);
            $table->string('reference', 120)->nullable();
            $table->date('paid_on');
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_payments');
        Schema::dropIfExists('partner_commissions');
        Schema::dropIfExists('partner_documents');
        Schema::dropIfExists('partner_applications');
        Schema::dropIfExists('partners');
    }
};
