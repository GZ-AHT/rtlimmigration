<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_meta', function (Blueprint $table) {
            $table->boolean('seo_enabled')->default(true)->after('seoable_id');
            $table->string('meta_keywords')->nullable()->after('meta_description');
            $table->boolean('nofollow')->default(false)->after('noindex');
            $table->string('twitter_card', 20)->nullable()->after('nofollow');
            $table->text('custom_schema')->nullable()->after('twitter_card');
        });

        // REQ §22.3: 301 / 302 redirect management.
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_url', 500);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // REQ §22.3: broken-link monitor. Each scan replaces the previous results.
        Schema::create('broken_links', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_label');
            $table->string('source_url', 500)->nullable();
            $table->string('target_url', 500);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broken_links');
        Schema::dropIfExists('redirects');
        Schema::table('seo_meta', function (Blueprint $table) {
            $table->dropColumn(['seo_enabled', 'meta_keywords', 'nofollow', 'twitter_card', 'custom_schema']);
        });
    }
};
