<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video consultation bookings. All instants are UTC. `active_slot` holds the
 * start time only while a booking is pending or confirmed and is unique, so
 * the database itself refuses a second active booking for the same start.
 * The history table also keeps the admins' internal notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('name', 120);
            $table->string('email')->index();
            $table->string('phone', 20);
            $table->string('subject', 160);
            $table->text('message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('timezone', 64);
            $table->string('status', 16)->default('pending')->index();
            $table->dateTime('active_slot')->nullable()->unique();
            $table->string('meeting_provider', 16)->nullable();
            $table->string('meeting_url', 500)->nullable();
            $table->timestamp('meeting_sent_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('consultation_booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 24);
            $table->text('note')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['consultation_booking_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_booking_events');
        Schema::dropIfExists('consultation_bookings');
    }
};
