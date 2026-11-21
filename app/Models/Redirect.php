<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * A 301 / 302 redirect from an old website path to a new address (REQ §22.3).
 *
 * @property int $id
 * @property string $from_path
 * @property string $to_url
 * @property int $status_code
 * @property bool $is_active
 * @property int $hits
 * @property Carbon|null $last_hit_at
 * @property string|null $note
 */
#[Fillable(['from_path', 'to_url', 'status_code', 'is_active', 'note', 'created_by'])]
class Redirect extends Model
{
    public const CACHE_KEY = 'redirects.map';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'status_code' => 'integer', 'hits' => 'integer', 'last_hit_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    /** "/Old-Page/?x=1" → "/old-page"; the key redirects are matched on. */
    public static function normalize(string $path): string
    {
        $path = strtolower((string) parse_url(trim($path), PHP_URL_PATH));

        return '/'.trim($path, '/');
    }

    /** @return array<string, array{id: int, to: string, status: int}> active redirects by path */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->where('is_active', true)->get(['id', 'from_path', 'to_url', 'status_code'])
            ->mapWithKeys(fn (self $r) => [$r->from_path => ['id' => $r->id, 'to' => $r->to_url, 'status' => $r->status_code]])->all());
    }
}
