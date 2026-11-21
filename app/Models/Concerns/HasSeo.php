<?php

namespace App\Models\Concerns;

use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** A record with its own SEO fields (REQ §4 / §5 / §15 "SEO Settings"). */
trait HasSeo
{
    /** @return MorphOne<SeoMeta, $this> */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    /** @param array<string, mixed>|null $data validated fields from SeoRules::rules() */
    public function saveSeo(?array $data): void
    {
        if ($data === null) {
            return;
        }

        $values = array_intersect_key($data, array_flip(SeoMeta::FIELDS));
        $values['noindex'] = (bool) ($values['noindex'] ?? false);
        $values['nofollow'] = (bool) ($values['nofollow'] ?? false);
        $values['seo_enabled'] = (bool) ($values['seo_enabled'] ?? true);

        $this->seo()->updateOrCreate([], $values);
    }

    /** @return array<string, mixed> */
    public function seoForForm(): array
    {
        $seo = $this->seo;

        return [
            'meta_title' => $seo->meta_title ?? '',
            'meta_description' => $seo->meta_description ?? '',
            'focus_keyword' => $seo->focus_keyword ?? '',
            'canonical_url' => $seo->canonical_url ?? '',
            'og_title' => $seo->og_title ?? '',
            'og_description' => $seo->og_description ?? '',
            'og_image' => $seo?->ogImage?->present(),
            'noindex' => (bool) $seo?->noindex,
            'seo_enabled' => $seo === null ? true : (bool) $seo->seo_enabled,
            'meta_keywords' => $seo->meta_keywords ?? '',
            'nofollow' => (bool) $seo?->nofollow,
            'twitter_card' => $seo->twitter_card ?? '',
            'custom_schema' => $seo->custom_schema ?? '',
        ];
    }
}
