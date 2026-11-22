<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One-time codes for email verification and password reset. Only a keyed hash is stored. */
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('purpose', 30);
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            // Password reset: proof that the code was entered, valid for the final step only.
            $table->string('grant_hash', 64)->nullable();
            $table->timestamp('grant_expires_at')->nullable();
            $table->timestamp('grant_used_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'purpose']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
