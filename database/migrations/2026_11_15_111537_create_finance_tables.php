<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5 finance (REQ §9, §12.3, §12.5): refunds, online gateway
     * transactions, webhook de-duplication and expenses. Amounts are integer
     * minor units, like the payment tables.
     */
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('amount_refunded')->default(0)->after('amount_paid');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('amount_refunded')->default(0)->after('amount');
        });

        // REQ §12.5 rule 8: every refund references the original payment.
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 32)->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('BDT');
            $table->string('reason', 500);
            $table->string('status', 16)->default('requested')->index(); // requested, approved, rejected, processed
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable()->index();
            // How the money went back to the student.
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });

        // One row per attempt to pay online. Failed and abandoned attempts
        // stay here; only a validated one becomes a payment.
        Schema::create('gateway_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 32);
            $table->string('tran_id', 64)->unique();
            $table->foreignId('payment_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('BDT');
            $table->string('status', 16)->default('initiated')->index(); // initiated, paid, review, failed, cancelled, invalid
            $table->string('session_key', 120)->nullable();
            $table->string('validation_id', 120)->nullable();
            $table->string('bank_tran_id', 120)->nullable();
            $table->string('card_type', 120)->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['payment_request_id', 'status']);
        });

        // REQ §12.5 rule 9: a callback delivered twice is recorded once.
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 32);
            $table->string('event_key', 64)->unique();
            $table->string('tran_id', 64)->nullable()->index();
            $table->string('source', 16); // ipn, return
            $table->json('payload')->nullable();
            $table->string('result', 32)->nullable();
            $table->unsignedInteger('deliveries')->default(1);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Roadmap Q12: simple expense records so income & expense reports work.
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('BDT');
            $table->date('spent_on')->index();
            $table->string('payee')->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('attachment_disk', 32)->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime_type', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('gateway_transactions');
        Schema::dropIfExists('refunds');

        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('amount_refunded'));
        Schema::table('payment_requests', fn (Blueprint $table) => $table->dropColumn('amount_refunded'));
    }
};
