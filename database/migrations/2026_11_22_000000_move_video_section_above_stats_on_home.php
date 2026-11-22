<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Homepage order: the video consultation section now sits above the
 * statistics counter. Moves it once on the existing homepage and leaves
 * the page alone if an editor already arranged it differently (the
 * section is missing, or already above the counter).
 */
return new class extends Migration
{
    public function up(): void
    {
        $page = DB::table('pages')->where('system_key', 'home')->first();

        if (! $page) {
            return;
        }

        $sections = json_decode((string) $page->sections, true);

        if (! is_array($sections)) {
            return;
        }

        $types = array_column($sections, 'type');
        $video = array_search('video', $types, true);
        $stats = array_search('stats', $types, true);

        if ($video === false || $stats === false || $video < $stats) {
            return;
        }

        $moved = array_splice($sections, $video, 1);
        array_splice($sections, $stats, 0, $moved);

        DB::table('pages')->where('id', $page->id)->update([
            'sections' => json_encode(array_values($sections), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function down(): void
    {
        // Order is editable content; nothing to undo.
    }
};
