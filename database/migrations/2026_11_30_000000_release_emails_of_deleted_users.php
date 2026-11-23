<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deleted users are soft-deleted so payments, files and history keep their
 * owner, but their email stayed in the unique `users.email` index and blocked
 * registration. The real address now lives in `original_email` and the login
 * email becomes an unusable placeholder. Existing deleted users are converted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('original_email')->nullable()->after('email')->index();
        });

        DB::table('users')->whereNotNull('deleted_at')->whereNull('original_email')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update([
                'original_email' => $user->email,
                'email' => User::placeholderEmail((int) $user->id),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('users')->whereNotNull('original_email')->whereNull('deleted_at')->update(['original_email' => null]);

        // Hand the real address back to deleted users when it is still free.
        DB::table('users')->whereNotNull('original_email')->orderBy('id')->each(function ($user) {
            if (! DB::table('users')->where('email', $user->original_email)->exists()) {
                DB::table('users')->where('id', $user->id)->update(['email' => $user->original_email]);
            }
        });

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('original_email'));
    }
};
