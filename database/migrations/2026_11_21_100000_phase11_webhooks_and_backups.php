<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // REQ §23.3 webhook management: where to send events, and what happened to each delivery.
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('url', 500);
            $table->text('secret');
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamp('last_delivered_at')->nullable();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event', 60);
            $table->uuid('delivery_id');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('succeeded')->default(false);
            $table->string('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['webhook_id', 'id']);
        });

        // REQ §23.5 backups: one row per file kept in storage/app/backups.
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10); // database | files
            $table->string('trigger', 10); // manual | scheduled | safety
            $table->string('filename')->unique();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status', 10)->default('running'); // running | done | failed
            $table->string('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
