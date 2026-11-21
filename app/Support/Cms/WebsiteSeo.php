<?php

namespace App\Support\Cms;

use App\Models\Media;
use App\Models\SeoMeta;
use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Str;

/**
 * Builds the <head> data for a public page (REQ §22): title, description,
 * canonical, robots, Open Graph, Twitter and JSON-LD. A page's own SEO fields
 * win, then the global SEO settings, then the page's own title and text.
 */
class WebsiteSeo
{
    /** A global SEO setting, falling back to its built-in default. */
    public static function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsRepository::class)->get("seo.{$key}", SiteSettings::defaults()["seo.{$key}"] ?? $default);
    }

    /**
     * @param  list<array{name: string, url: string}>  $crumbs  trail after "Home"; blank derives it from the title
     * @param  array{published?: string|null, modified?: string|null, author?: string|null}  $article
     * @return array{title: string, description: string, canonical: string, og_title: string, og_description: string, image: string|null, noindex: bool, type: string, robots: string, keywords: string|null, twitter_card: string, twitter_site: string|null, hreflang: string|null, enabled: bool, schema: list<array<string, mixed>>}
     */
    public static function make(string $title, ?string $description, ?SeoMeta $seo = null, ?Media $image = null, string $type = 'website', bool $noindex = false, array $crumbs = [], array $article = []): array
    {
        $settings = app(SettingsRepository::class);
        $enabled = (bool) self::setting('enabled', true);
        $own = $enabled && $seo?->seo_enabled !== false ? $seo : null;
        $siteName = (string) $settings->get('general.site_name', config('app.name'));
        $isHome = request()->path() === '/';
        $defaultTitle = $enabled && $isHome ? (string) self::setting('default_title') : '';
        $metaTitle = $own?->meta_title ?: ($defaultTitle !== '' ? $defaultTitle : $title);
        $fullTitle = $own?->meta_title === null && $defaultTitle !== '' ? $defaultTitle : ($metaTitle === $siteName ? trim($siteName.' | '.$settings->get('general.tagline', ''), ' |') : $metaTitle.' | '.$siteName);

        $description = Str::limit((string) ($own?->meta_description ?: $description ?: ($enabled ? self::setting('default_description') : null) ?: $settings->get('general.description', '')), 300, '');
        $canonical = $own?->canonical_url ?: self::canonicalFor();
        $ogImage = ($own->ogImage ?? $image)?->url() ?? ($enabled ? self::globalImage() : null);

        $globalRobots = $enabled ? (string) self::setting('default_robots', 'index,follow') : 'index,follow';
        $siteHidden = $enabled && (bool) self::setting('noindex_site', false);
        $index = ! ($noindex || $siteHidden || $own?->noindex) && ! str_starts_with($globalRobots, 'noindex');
        $follow = ! ($siteHidden || $own?->nofollow) && ! str_ends_with($globalRobots, 'nofollow');

        $seoData = [
            'title' => $fullTitle,
            'description' => $description,
            'canonical' => $canonical,
            'og_title' => $own?->og_title ?: ($isHome && $enabled && filled(self::setting('og_title')) ? (string) self::setting('og_title') : $metaTitle),
            'og_description' => Str::limit((string) ($own?->og_description ?: $own?->meta_description ?: ($isHome && $enabled ? self::setting('og_description') : null) ?: $description), 300, ''),
            'image' => $ogImage,
            'noindex' => ! $index,
            'type' => $type,
            'robots' => ($index ? 'index' : 'noindex').', '.($follow ? 'follow' : 'nofollow').($index ? ', max-image-preview:large' : ''),
            'keywords' => $enabled ? ($own?->meta_keywords ?: (string) self::setting('default_keywords') ?: null) : null,
            'twitter_card' => $own?->twitter_card ?: ($ogImage ? (string) self::setting('twitter_card', 'summary_large_image') : 'summary'),
            'twitter_site' => $enabled && filled(self::setting('twitter_site')) ? '@'.ltrim((string) self::setting('twitter_site'), '@') : null,
            'hreflang' => $enabled && self::setting('hreflang') ? (string) $settings->get('general.default_language', 'en') : null,
            'enabled' => $enabled,
            'schema' => [],
        ];

        if ($enabled && $index) {
            $seoData['schema'] = self::schema($seoData, $title, $isHome, $crumbs, $article, $own);
        }

        return $seoData;
    }

    /** Canonical address: the default site address plus this page's path, without query strings. */
    public static function canonicalFor(?string $path = null): string
    {
        $path = '/'.ltrim($path ?? request()->path(), '/');
        $base = rtrim((string) (self::setting('canonical_base') ?: ''), '/');

        $canonical = $base !== '' ? $base.($path === '/' ? '' : $path) : url($path === '/' ? '/' : $path);

        // Pagination SEO (REQ §22.3): page 2 onwards is its own address, so it points at itself, not at page 1.
        $page = request()->query('page');

        return is_string($page) && ctype_digit($page) && (int) $page > 1 ? $canonical.'?page='.(int) $page : $canonical;
    }

    private static function globalImage(): ?string
    {
        $id = self::setting('og_image');

        return is_numeric($id) ? Media::query()->find((int) $id)?->url() : null;
    }

    /**
     * @param  array<string, mixed>  $seo
     * @param  list<array{name: string, url: string}>  $crumbs
     * @param  array{published?: string|null, modified?: string|null, author?: string|null}  $article
     * @return list<array<string, mixed>>
     */
    private static function schema(array $seo, string $title, bool $isHome, array $crumbs, array $article, ?SeoMeta $own): array
    {
        $settings = app(SettingsRepository::class);
        $siteName = (string) $settings->get('general.site_name', config('app.name'));
        $home = self::canonicalFor('/');
        $logo = Media::query()->find((int) $settings->get('branding.logo'))?->url();
        $nodes = [];

        $sameAs = array_values(array_filter(array_map(fn ($k) => $settings->get("social.{$k}"), ['facebook', 'instagram', 'linkedin', 'youtube', 'x', 'tiktok'])));

        if ($isHome && self::setting('schema_organization', true)) {
            $nodes[] = array_filter([
                '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $siteName, 'url' => $home,
                'logo' => $logo, 'email' => $settings->get('general.contact_email'), 'telephone' => $settings->get('general.phone'),
                'sameAs' => $sameAs ?: null,
                'parentOrganization' => filled($pub = self::setting('publisher')) ? ['@type' => 'Organization', 'name' => $pub] : null,
            ]);
            $nodes[] = array_filter([
                '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $siteName, 'url' => $home,
                'description' => $settings->get('general.description') ?: null, 'inLanguage' => $settings->get('general.default_language', 'en'),
                'publisher' => filled($pub = self::setting('publisher')) ? ['@type' => 'Organization', 'name' => $pub] : null,
            ]);
        }

        if ($isHome && self::setting('schema_local_business', true) && filled($settings->get('general.address'))) {
            $nodes[] = array_filter([
                '@context' => 'https://schema.org', '@type' => (string) self::setting('business_type', 'ProfessionalService'),
                'name' => $siteName, 'url' => $home, 'image' => $seo['image'] ?? $logo,
                'telephone' => $settings->get('general.phone'), 'email' => $settings->get('general.contact_email'),
                'address' => ['@type' => 'PostalAddress', 'streetAddress' => $settings->get('general.address'), 'addressCountry' => 'BD'],
                'openingHours' => $settings->get('general.business_hours') ?: null,
                'priceRange' => self::setting('price_range') ?: null, 'sameAs' => $sameAs ?: null,
            ]);
        }

        if (! $isHome) {
            $trail = $crumbs ?: [['name' => $title, 'url' => $seo['canonical']]];
            $items = [['name' => 'Home', 'url' => $home], ...$trail];
            $nodes[] = [
                '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => $c['url']], $items, array_keys($items)),
            ];
        }

        if ($seo['type'] === 'article') {
            $nodes[] = array_filter([
                '@context' => 'https://schema.org', '@type' => 'Article', 'headline' => Str::limit($title, 110, ''),
                'description' => $seo['description'] ?: null, 'image' => $seo['image'], 'mainEntityOfPage' => $seo['canonical'],
                'datePublished' => $article['published'] ?? null, 'dateModified' => $article['modified'] ?? ($article['published'] ?? null),
                'author' => filled($article['author'] ?? null) ? ['@type' => 'Person', 'name' => $article['author']] : null,
                'publisher' => array_filter(['@type' => 'Organization', 'name' => $siteName, 'logo' => $logo ? ['@type' => 'ImageObject', 'url' => $logo] : null]),
            ]);
        }

        // Structured data written by an editor (REQ §22.2): a JSON-LD object or a list of them.
        if (filled($own?->custom_schema) && is_array($custom = json_decode((string) $own->custom_schema, true))) {
            foreach (array_is_list($custom) ? $custom : [$custom] as $node) {
                if (is_array($node)) {
                    $nodes[] = ['@context' => 'https://schema.org', ...$node];
                }
            }
        }

        return $nodes;
    }
}
