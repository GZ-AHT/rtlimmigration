<?php

namespace App\Support\System;

use App\Http\Controllers\Admin\MediaController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Read-only facts about the server for System → Settings → Overview (REQ §23.1).
 * Passwords, keys, hosts and users are never included.
 */
class SystemInfo
{
    /** @return array<string, array<string, string>> section => label => value */
    public static function overview(): array
    {
        return [
            'Application' => [
                'Laravel' => Application::VERSION,
                'PHP' => PHP_VERSION,
                'Environment' => (string) config('app.env'),
                'Debug mode' => config('app.debug') ? 'On (turn off in production)' : 'Off',
                'Timezone' => (string) config('app.timezone'),
                'Address' => (string) config('app.url'),
            ],
            'Database' => [
                'Driver' => (string) config('database.default'),
                'Database' => (string) config('database.connections.'.config('database.default').'.database'),
                'Server version' => self::dbVersion(),
            ],
            'Drivers' => [
                'Cache' => (string) config('cache.default'),
                'Queue' => (string) config('queue.default'),
                'Sessions' => (string) config('session.driver'),
                'Mail' => (string) config('mail.default'),
                'File storage' => (string) config('filesystems.default'),
            ],
            'Uploads' => [
                'PHP upload limit' => (string) ini_get('upload_max_filesize'),
                'PHP form limit' => (string) ini_get('post_max_size'),
                'Image limit' => (MediaController::MAX_KB / 1024).' MB',
                'Video limit' => (MediaController::VIDEO_MAX_KB / 1024).' MB',
                'Image types' => strtoupper(implode(', ', MediaController::MIMES)),
            ],
        ];
    }

    /** @return array{public: string, private: string, backups: string, free: string, total: string} */
    public static function storage(): array
    {
        return Cache::remember('system.storage-usage', now()->addMinutes(10), fn () => [
            'public' => self::format(self::size(storage_path('app/public'))),
            'private' => self::format(self::size(storage_path('app/private'))),
            'backups' => self::format(self::size(storage_path('app/backups'))),
            'free' => self::format((int) @disk_free_space(storage_path())),
            'total' => self::format((int) @disk_total_space(storage_path())),
        ]);
    }

    private static function dbVersion(): string
    {
        try {
            return (string) DB::selectOne('select version() as v')->v;
        } catch (Throwable) {
            return 'Unknown';
        }
    }

    private static function size(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $bytes = 0;

        foreach (File::allFiles($path) as $file) {
            $bytes += $file->getSize();
        }

        return $bytes;
    }

    public static function format(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $i => $unit) {
            if ($bytes < 1024 ** ($i + 1) || $unit === 'TB') {
                return round($bytes / 1024 ** $i, $i === 0 ? 0 : 1).' '.$unit;
            }
        }

        return $bytes.' B';
    }
}
