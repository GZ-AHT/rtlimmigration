<?php

namespace App\Support\Content;

use App\Models\Media;
use Illuminate\Validation\Rule;

/** Shared validation for the SEO panel on posts, pages and universities. */
class SeoRules
{
    /** @return array<string, list<mixed>> */
    public static function rules(string $prefix = 'seo'): array
    {
        return [
            $prefix => ['nullable', 'array'],
            "{$prefix}.meta_title" => ['nullable', 'string', 'max:70'],
            "{$prefix}.meta_description" => ['nullable', 'string', 'max:170'],
            "{$prefix}.focus_keyword" => ['nullable', 'string', 'max:100'],
            "{$prefix}.canonical_url" => ['nullable', 'url:https,http', 'max:255'],
            "{$prefix}.og_title" => ['nullable', 'string', 'max:100'],
            "{$prefix}.og_description" => ['nullable', 'string', 'max:300'],
            "{$prefix}.og_image_id" => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
            "{$prefix}.noindex" => ['boolean'],
        ];
    }
}
