<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The name a mailbox signs in with on the mail server, when it is not simply its address. */
    public function up(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->string('username', 190)->nullable()->after('local_part');
        });
    }

    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
