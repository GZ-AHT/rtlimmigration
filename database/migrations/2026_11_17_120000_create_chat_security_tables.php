<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chat security: moderation of messages, guest (website) chat sessions,
 * blocked IP addresses, and a security event log.
 *
 * Messages are never deleted by moderation. A held message stays in the
 * thread with its status, and every change of status is kept in
 * chat_moderation_events for audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            // Bumped whenever a message is added or its moderation changes, so
            // polling picks up both with one integer cursor.
            $table->unsignedBigInteger('last_seq')->default(0)->after('last_message_at');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('seq')->default(0)->after('chat_conversation_id');
            $table->string('moderation_status', 16)->default('approved')->after('client_id');
            $table->json('moderation_reasons')->nullable()->after('moderation_status');
            $table->foreignId('reviewed_by')->nullable()->after('moderation_reasons')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');

            $table->index(['chat_conversation_id', 'seq']);
            $table->index(['moderation_status', 'id']);
        });

        // Existing messages: IDs already increase within each conversation.
        DB::table('chat_messages')->update(['seq' => DB::raw('id')]);
        DB::table('chat_conversations')->update(['last_seq' => DB::raw('coalesce(last_message_id, 0)')]);

        Schema::create('chat_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->nullable()->constrained()->cascadeOnDelete();
            // SHA-256 of the random token kept in the visitor's encrypted cookie.
            $table->char('token_hash', 64)->unique();
            $table->string('name', 120);
            $table->string('email')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('suspended_until')->nullable();
            $table->string('suspension_reason', 255)->nullable();
            $table->foreignId('linked_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('ip_address');
        });

        Schema::create('chat_ip_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('reason', 255);
            $table->boolean('automatic')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('chat_security_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('severity', 16)->default('info');
            $table->string('ip_address', 45)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chat_guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chat_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chat_message_id')->nullable()->constrained()->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['type', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('chat_moderation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chat_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->json('reasons')->nullable();
            $table->text('note')->nullable();
            // Null when the automatic checks made the decision.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['chat_message_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_moderation_events');
        Schema::dropIfExists('chat_security_events');
        Schema::dropIfExists('chat_ip_blocks');
        Schema::dropIfExists('chat_guests');

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['chat_conversation_id', 'seq']);
            $table->dropIndex(['moderation_status', 'id']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['seq', 'moderation_status', 'moderation_reasons', 'reviewed_at']);
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn('last_seq');
        });
    }
};
