<?php

namespace App\Support\System;

/** Reads the end of the application log for System → Settings → Logs (REQ §23.1 error and system logs). */
class LogReader
{
    /** Bytes read from the end of the newest log file. */
    private const TAIL = 262144;

    public const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    /** @return array{file: string|null, entries: list<array{time: string, level: string, message: string, detail: string}>} newest first */
    public static function read(?string $level = null, int $limit = 100): array
    {
        $files = glob(storage_path('logs/*.log')) ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $file = $files[0] ?? null;

        if ($file === null) {
            return ['file' => null, 'entries' => []];
        }

        $size = (int) filesize($file);
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return ['file' => basename($file), 'entries' => []];
        }

        fseek($handle, max(0, $size - self::TAIL));
        $text = (string) stream_get_contents($handle);
        fclose($handle);

        // Entries start with "[2026-11-21 10:00:00] local.ERROR: message".
        $parts = preg_split('/^(?=\[\d{4}-\d{2}-\d{2}[ T][\d:.+\-Z]+\] [\w-]+\.\w+: )/m', $text) ?: [];
        $entries = [];

        foreach ($parts as $part) {
            if (! preg_match('/^\[([^\]]+)\] [\w-]+\.(\w+): (.*)$/s', trim($part), $m)) {
                continue;
            }

            $entryLevel = strtolower($m[2]);

            if ($level !== null && $level !== '' && $entryLevel !== $level) {
                continue;
            }

            [$headline, $detail] = array_pad(explode("\n", $m[3], 2), 2, '');
            $entries[] = ['time' => $m[1], 'level' => $entryLevel, 'message' => mb_substr($headline, 0, 600), 'detail' => mb_substr(trim($detail), 0, 2000)];
        }

        return ['file' => basename($file), 'entries' => array_slice(array_reverse($entries), 0, $limit)];
    }
}
