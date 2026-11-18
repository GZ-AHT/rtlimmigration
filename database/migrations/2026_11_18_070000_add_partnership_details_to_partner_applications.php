<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra questions on the redesigned website partner application (owner's
 * reference "RTL Bangladesh — Partner application.png", REQ §16).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            $table->string('job_title', 120)->nullable()->after('contact_name');
            $table->string('partnership_interest', 40)->nullable()->after('type');
            $table->string('years_in_operation', 20)->nullable()->after('partnership_interest');
            // Destination country ids the partner sends students to.
            $table->json('destinations')->nullable()->after('years_in_operation');
        });
    }

    public function down(): void
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'partnership_interest', 'years_in_operation', 'destinations']);
        });
    }
};
