<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * REQ §18 live chat (Tawk.to). Tawk.to holds the live conversation; these
     * tables keep what its webhooks tell us: one row per chat or offline
     * message, and a log of every webhook delivery.
     */
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16)->default('chat');
            // Tawk.to chat ID, or ticket ID for an offline message.
            $table->string('external_id', 64);
            $table->string('reference', 32)->nullable();
            $table->string('status', 16)->default('open')->index();
            $table->string('property_id', 64)->nullable();
            $table->string('property_name')->nullable();
            $table->string('visitor_name')->nullable();
            $table->string('visitor_email')->nullable()->index();
            $table->string('visitor_city')->nullable();
            $table->string('visitor_country', 8)->nullable();
            $table->string('subject')->nullable();
            $table->text('first_message')->nullable();
            $table->json('messages')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->json('tags')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'external_id']);
        });

        Schema::create('chat_webhook_logs', function (Blueprint $table) {
            $table->id();
            // Tawk.to's X-Hook-Event-Id: the same on every retry of one event.
            $table->string('event_id', 128)->nullable()->unique();
            $table->string('event', 64)->nullable()->index();
            $table->string('external_id', 64)->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->json('payload')->nullable();
            $table->string('result')->nullable();
            $table->unsignedInteger('deliveries')->default(1);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_webhook_logs');
        Schema::dropIfExists('chat_conversations');
    }
};
