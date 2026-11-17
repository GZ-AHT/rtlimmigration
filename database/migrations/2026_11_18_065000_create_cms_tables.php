<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ §5 Page Management (section builder + rich text, roadmap Q7),
 * revisions and scheduling, and REQ §21.3 dynamic menus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            // Built-in pages (home, about, contact…) keep their slug and can't be deleted.
            $table->string('system_key', 40)->nullable()->unique();
            $table->string('category', 60)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            // public | members (signed-in users) | hidden (direct link only, not in search)
            $table->string('visibility', 20)->default('public');
            $table->json('sections');
            $table->text('custom_css')->nullable();
            $table->text('custom_js')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->json('sections');
            $table->text('custom_css')->nullable();
            $table->text('custom_js')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            // main | footer | top | mobile | footer_services | custom
            $table->string('location', 40)->unique();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label', 80);
            // page | url | route
            $table->string('link_type', 20)->default('url');
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url')->nullable();
            $table->boolean('new_tab')->default(false);
            $table->boolean('is_active')->default(true);
            // everyone | guests | auth | roles
            $table->string('visibility', 20)->default('everyone');
            $table->json('roles')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('page_revisions');
        Schema::dropIfExists('pages');
    }
};
