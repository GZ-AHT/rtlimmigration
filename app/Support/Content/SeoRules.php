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
            "{$prefix}.nofollow" => ['boolean'],
            "{$prefix}.seo_enabled" => ['boolean'],
            "{$prefix}.meta_keywords" => ['nullable', 'string', 'max:255'],
            "{$prefix}.twitter_card" => ['nullable', 'in:summary,summary_large_image'],
            "{$prefix}.custom_schema" => ['nullable', 'string', 'max:10000', function ($a, $v, $fail) {
                if (filled($v) && ! (is_array($d = json_decode((string) $v, true)) && json_last_error() === JSON_ERROR_NONE)) {
                    $fail(__('Custom structured data must be valid JSON (a JSON-LD object or a list of objects).'));
                }
            }],
        ];
    }
}
