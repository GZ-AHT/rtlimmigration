<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website chat asks for a phone number (stored as E.164, e.g. +8801712345678)
 * instead of an email, and a chat can be handed over from the automatic
 * assistant to a person (handed_over_at). Nothing existing is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->string('visitor_phone', 20)->nullable()->index()->after('visitor_email');
            $table->timestamp('handed_over_at')->nullable()->after('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropIndex(['visitor_phone']);
            $table->dropColumn(['visitor_phone', 'handed_over_at']);
        });
    }
};
