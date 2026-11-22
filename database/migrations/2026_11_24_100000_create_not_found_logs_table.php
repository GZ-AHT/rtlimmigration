<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // REQ §22.3: addresses visitors asked for that do not exist, so they can be redirected.
        Schema::create('not_found_logs', function (Blueprint $table) {
            $table->id();
            $table->string('path', 191)->unique();
            $table->unsignedInteger('hits')->default(1);
            $table->string('referrer', 255)->nullable();
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->index('hits');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_logs');
    }
};
