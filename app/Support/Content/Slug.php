<?php

namespace App\Support\Content;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

/** URL slugs that stay unique within a table (posts, pages, universities…). */
class Slug
{
    /** @param class-string<Model> $model */
    public static function unique(string $model, string $source, ?int $ignoreId = null, string $column = 'slug', int $max = 200): string
    {
        $base = Str::limit(Str::slug($source), $max, '') ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 2;

        while (self::taken($model, $column, $slug, $ignoreId)) {
            $slug = Str::limit($base, $max - 6, '').'-'.$i++;
        }

        return $slug;
    }

    /** @param class-string<Model> $model */
    private static function taken(string $model, string $column, string $slug, ?int $ignoreId): bool
    {
        $query = $model::query()->where($column, $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));

        if (method_exists($model, 'bootSoftDeletes')) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $query->exists();
    }
}
