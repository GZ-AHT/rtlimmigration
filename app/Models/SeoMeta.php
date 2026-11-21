<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Search and sharing fields for one record (REQ §4, §5, §15 SEO).
 *
 * @property int $id
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $focus_keyword
 * @property string|null $canonical_url
 * @property string|null $og_title
 * @property string|null $og_description
 * @property int|null $og_image_id
 * @property bool $noindex
 * @property bool $seo_enabled
 * @property string|null $meta_keywords
 * @property bool $nofollow
 * @property string|null $twitter_card
 * @property string|null $custom_schema
 * @property-read Media|null $ogImage
 */
#[Table('seo_meta')]
#[Fillable(['meta_title', 'meta_description', 'focus_keyword', 'canonical_url', 'og_title', 'og_description', 'og_image_id', 'noindex', 'seo_enabled', 'meta_keywords', 'nofollow', 'twitter_card', 'custom_schema'])]
class SeoMeta extends Model
{
    public const FIELDS = ['meta_title', 'meta_description', 'focus_keyword', 'canonical_url', 'og_title', 'og_description', 'og_image_id', 'noindex', 'seo_enabled', 'meta_keywords', 'nofollow', 'twitter_card', 'custom_schema'];

    protected function casts(): array
    {
        return ['noindex' => 'boolean', 'nofollow' => 'boolean', 'seo_enabled' => 'boolean'];
    }

    /** @return MorphTo<Model, $this> */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Media, $this> */
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_id');
    }
}
