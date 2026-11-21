<?php

namespace App\Support\Backups;

use App\Models\Backup;
use App\Support\System\SystemSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Database and file backups (REQ §23.5). A database backup is a gzipped SQL
 * file written by this class, one statement per line, so restoring it needs
 * no outside tools. Backups sit in storage/app/backups, outside the web root.
 * They contain personal data and password hashes: keep copies somewhere safe.
 */
class BackupService
{
    private const HEADER = '-- RTL-BACKUP v1 mysql';

    /** Tables whose contents are temporary, or that must survive a restore. */
    private const SKIP_DATA = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    private const SKIP_TABLE = ['backups'];

    public static function directory(): string
    {
        $dir = (string) config('filesystems.backups_path', storage_path('app/backups'));

        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        return $dir;
    }

    public static function path(Backup $backup): string
    {
        return self::directory().'/'.basename($backup->filename);
    }

    /** Creates a database or files backup and records it. Failures are recorded, not thrown. */
    public function create(string $type, string $trigger, ?int $userId = null): Backup
    {
        $stamp = now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
        $filename = $type === 'database' ? "db-{$stamp}.sql.gz" : "files-{$stamp}.zip";
        $backup = Backup::create(['type' => $type, 'trigger' => $trigger, 'filename' => $filename, 'status' => 'running', 'created_by' => $userId]);

        try {
            set_time_limit(0);
            $type === 'database' ? $this->dumpDatabase(self::path($backup)) : $this->zipFiles(self::path($backup));
            $backup->update(['status' => 'done', 'size' => (int) filesize(self::path($backup))]);
        } catch (Throwable $e) {
            @unlink(self::path($backup));
            $backup->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 200, '')]);
            report($e);
        }

        return $backup;
    }

    public function dumpDatabase(string $path): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Database backups need a MySQL database.');
        }

        $pdo = DB::connection()->getPdo();
        $gz = gzopen($path, 'wb6');

        if ($gz === false) {
            throw new RuntimeException('Could not write the backup file.');
        }

        try {
            gzwrite($gz, self::HEADER."\n-- ".now()->toIso8601String()."\nSET FOREIGN_KEY_CHECKS=0;\n");

            foreach (DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
                $table = (string) array_values((array) $row)[0];

                if (in_array($table, self::SKIP_TABLE, true)) {
                    continue;
                }

                $create = (string) ((array) DB::selectOne("SHOW CREATE TABLE `{$table}`"))['Create Table'];
                gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n".preg_replace('/\s*\R\s*/', ' ', $create).";\n");

                if (in_array($table, self::SKIP_DATA, true)) {
                    continue;
                }

                $binary = collect(DB::select("SHOW COLUMNS FROM `{$table}`"))->filter(fn ($c) => preg_match('/blob|binary/i', (string) $c->Type) === 1)->pluck('Field')->all();
                $columns = null;

                DB::table($table)->cursor()->chunk(200)->each(function ($chunk) use ($gz, $pdo, $table, &$columns, $binary) {
                    $values = [];

                    foreach ($chunk as $record) {
                        $record = (array) $record;
                        $columns ??= implode(',', array_map(fn ($c) => "`{$c}`", array_keys($record)));
                        $values[] = '('.implode(',', array_map(
                            fn ($col, $v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : (in_array($col, $binary, true) ? '0x'.bin2hex((string) $v) : $pdo->quote((string) $v))),
                            array_keys($record), $record,
                        )).')';
                    }

                    gzwrite($gz, "INSERT INTO `{$table}` ({$columns}) VALUES ".implode(',', $values).";\n");
                });
            }

            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n-- END\n");
        } finally {
            gzclose($gz);
        }
    }

    private function zipFiles(string $path): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not write the backup file.');
        }

        foreach (['public', 'private'] as $folder) {
            $root = storage_path("app/{$folder}");

            if (! is_dir($root)) {
                continue;
            }

            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) {
                    $zip->addFile($file->getPathname(), $folder.'/'.substr($file->getPathname(), strlen($root) + 1));
                }
            }
        }

        $zip->close();
    }

    /**
     * Restores a database backup made by this class. Returns the number of statements run.
     * Make a safety backup first; this replaces every table in the backup.
     */
    public function restore(Backup $backup): int
    {
        abort_unless($backup->type === 'database' && $backup->status === 'done', 422);

        $path = self::path($backup);
        $gz = is_file($path) ? gzopen($path, 'rb') : false;

        if ($gz === false || trim((string) gzgets($gz)) !== self::HEADER) {
            throw new RuntimeException('That file is not a valid backup.');
        }

        set_time_limit(0);
        $count = 0;

        try {
            while (($line = gzgets($gz)) !== false) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '--')) {
                    continue;
                }

                // Only the statements this class writes are ever run.
                if (! preg_match('/^(SET FOREIGN_KEY_CHECKS=[01]|DROP TABLE IF EXISTS `|CREATE TABLE `|INSERT INTO `)/', $line)) {
                    throw new RuntimeException('Unexpected content in the backup file.');
                }

                DB::connection()->getPdo()->exec($line);
                $count++;
            }
        } finally {
            gzclose($gz);
            DB::connection()->getPdo()->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        return $count;
    }

    /** Deletes automatic backups past their retention, keeping the newest of each type. */
    public function prune(): int
    {
        $days = (int) SystemSettings::get('backups.retention_days', 14);
        $removed = 0;

        foreach (['database', 'files'] as $type) {
            $keep = Backup::query()->where('type', $type)->where('status', 'done')->latest('id')->value('id');

            Backup::query()->where('type', $type)->where('trigger', '!=', 'manual')->whereKeyNot($keep ?? 0)
                ->where('created_at', '<', now()->subDays($days))->get()->each(function (Backup $b) use (&$removed) {
                    $this->delete($b);
                    $removed++;
                });
        }

        return $removed;
    }

    public function delete(Backup $backup): void
    {
        @unlink(self::path($backup));
        $backup->delete();
    }
}
