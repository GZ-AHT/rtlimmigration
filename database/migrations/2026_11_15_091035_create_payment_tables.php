<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * REQ §12 multi-step payments and §26 (payment_requests, payments,
     * payment_types). Amounts are integer minor units (poisha for BDT).
     * Payment records are never hard-deleted (REQ §12.5 rule 10).
     */
    public function up(): void
    {
        Schema::create('payment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('default_amount')->nullable();
            // Raise this request automatically when a file is opened (REQ §11 step 6).
            $table->boolean('generate_on_file_open')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('kind', 24); // bank_transfer, card, cash, mobile_banking, gateway, manual
            // Shown to students, e.g. bank account or bKash merchant number.
            $table->text('instructions')->nullable();
            $table->boolean('requires_reference')->default(true);
            $table->boolean('requires_evidence')->default(false);
            // Students may choose it when submitting a payment themselves.
            $table->boolean('student_selectable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 32)->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_file_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_type_id')->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->date('due_date')->nullable()->index();
            $table->string('status', 20)->default('unpaid')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });

        // One row per payment made or submitted against a request.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('BDT');
            $table->string('transaction_reference', 120)->nullable();
            $table->date('paid_on');
            $table->string('evidence_disk', 32)->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_original_name')->nullable();
            $table->string('evidence_mime_type', 120)->nullable();
            $table->string('status', 16)->default('pending')->index(); // pending, verified, confirmed, rejected
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('receipt_number', 32)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['payment_method_id', 'transaction_reference']);
        });

        // REQ §12.5 rule 7: every status change is recorded.
        Schema::create('payment_status_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_status_histories');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_requests');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('payment_types');
    }
};
