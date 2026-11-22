<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ §24 "Website Traffic Reports": one row per page, day and anonymous
 * visitor. The visitor hash rotates daily and is never stored with an IP
 * address, so nothing here can follow a person across days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->date('viewed_on');
            $table->string('path', 191);
            $table->char('visitor_hash', 40);
            $table->unsignedInteger('hits')->default(1);

            $table->unique(['viewed_on', 'path', 'visitor_hash']);
            $table->index(['viewed_on', 'visitor_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
