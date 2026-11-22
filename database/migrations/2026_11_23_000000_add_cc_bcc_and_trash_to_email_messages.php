<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            // Extra recipients, as lists of addresses.
            $table->json('cc')->nullable()->after('to_name');
            $table->json('bcc')->nullable()->after('cc');
            // Hidden from the sender's "Sent from the system" list, never from
            // delivery logs, a student's history or reports. Restorable.
            $table->timestamp('trashed_at')->nullable()->after('click_count');
            $table->index(['sent_by', 'trashed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropIndex(['sent_by', 'trashed_at']);
            $table->dropColumn(['cc', 'bcc', 'trashed_at']);
        });
    }
};
