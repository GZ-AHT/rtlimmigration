<?php

namespace App\Support\Cms;

use App\Models\Media;
use App\Models\SeoMeta;
use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Str;

/**
 * Builds the <head> tags for a public page from its SEO fields (REQ §22),
 * falling back to the page's own title, text and image. Global SEO,
 * sitemaps and schema arrive in Phase 10.
 */
class WebsiteSeo
{
    /** @return array{title: string, description: string, canonical: string, og_title: string, og_description: string, image: string|null, noindex: bool, type: string} */
    public static function make(string $title, ?string $description, ?SeoMeta $seo = null, ?Media $image = null, string $type = 'website', bool $noindex = false): array
    {
        $settings = app(SettingsRepository::class);
        $siteName = (string) $settings->get('general.site_name', config('app.name'));
        $metaTitle = $seo?->meta_title ?: $title;

        return [
            'title' => $metaTitle === $siteName ? trim($siteName.' | '.$settings->get('general.tagline', ''), ' |') : $metaTitle.' | '.$siteName,
            'description' => Str::limit((string) ($seo?->meta_description ?: $description ?: $settings->get('general.description', '')), 300, ''),
            'canonical' => $seo?->canonical_url ?: url()->current(),
            'og_title' => $seo?->og_title ?: $metaTitle,
            'og_description' => Str::limit((string) ($seo?->og_description ?: $seo?->meta_description ?: $description), 300, ''),
            'image' => ($seo->ogImage ?? $image)?->url(),
            'noindex' => $noindex || (bool) $seo?->noindex,
            'type' => $type,
        ];
    }
}
