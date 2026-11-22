<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12 performance: the reports filter on a date range, so the dates
 * they use get an index. Without these every report reads whole tables.
 */
return new class extends Migration
{
    /** table => columns that reports filter by date. */
    private const INDEXES = [
        'students' => ['created_at'],
        'applications' => ['created_at', 'decided_at'],
        'tickets' => ['created_at'],
        'partner_applications' => ['created_at'],
        'chat_conversations' => ['created_at'],
        'leads' => ['assigned_at', 'converted_at'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->dropIndex([$column]);
                }
            });
        }
    }
};
