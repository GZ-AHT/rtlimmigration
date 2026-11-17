<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal messaging between a student and staff, kept in this system
 * because Tawk.to has no API for sending agent replies. Each student has
 * one continuous thread: a chat_conversations row of kind "portal".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            // Lets a poll answer "anything new?" from the conversation row alone.
            $table->unsignedBigInteger('last_message_id')->nullable()->after('message_count');
            $table->timestamp('last_message_at')->nullable()->after('last_message_id');
            $table->index(['kind', 'last_message_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sender_type', 16);
            $table->text('body')->nullable();
            // Sent by the browser with each message; a retry reuses it, so it is stored once.
            $table->uuid('client_id')->nullable();
            $table->timestamps();

            $table->unique(['chat_conversation_id', 'client_id']);
            $table->index(['chat_conversation_id', 'id']);
        });

        Schema::create('chat_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        Schema::create('chat_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamps();

            $table->unique(['chat_conversation_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_reads');
        Schema::dropIfExists('chat_message_attachments');
        Schema::dropIfExists('chat_messages');

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropIndex(['kind', 'last_message_at']);
            $table->dropColumn(['last_message_id', 'last_message_at']);
        });
    }
};
