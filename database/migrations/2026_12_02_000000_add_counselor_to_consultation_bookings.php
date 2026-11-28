<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The counselor who takes a consultation, and whether its meeting link is the
 * saved default (which follows the setting until the booking is confirmed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_bookings', function (Blueprint $table) {
            $table->foreignId('counselor_id')->nullable()->after('lead_id')->constrained('users')->nullOnDelete();
            $table->boolean('meeting_is_default')->default(false)->after('meeting_url');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('counselor_id');
            $table->dropColumn('meeting_is_default');
        });
    }
};
