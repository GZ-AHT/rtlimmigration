<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\SkillAssessment;
use App\Models\University;
use App\Support\Cms\WebsiteSeo;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/** REQ §22.3: the dynamic XML sitemap and robots.txt. */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        abort_unless(WebsiteSeo::setting('enabled', true) && WebsiteSeo::setting('sitemap_enabled', true) && ! WebsiteSeo::setting('noindex_site', false), 404);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($this->entries() as $e) {
            $xml .= '  <url><loc>'.htmlspecialchars($e['loc'], ENT_XML1).'</loc>'
                .($e['lastmod'] ? '<lastmod>'.$e['lastmod'].'</lastmod>' : '')
                .'<changefreq>'.$e['changefreq'].'</changefreq><priority>'.$e['priority'].'</priority></url>'."\n";
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $custom = trim((string) WebsiteSeo::setting('robots_txt', ''));
        $hidden = (bool) WebsiteSeo::setting('noindex_site', false);

        if ($hidden) {
            $body = "User-agent: *\nDisallow: /\n";
        } elseif ($custom !== '') {
            $body = $custom."\n";
        } else {
            $body = "User-agent: *\nDisallow: /admin\nDisallow: /dashboard\nDisallow: /settings\nDisallow: /email\nDisallow: /chat\nDisallow: /payments\n";
            $body .= WebsiteSeo::setting('sitemap_enabled', true) ? "\nSitemap: ".WebsiteSeo::canonicalFor('sitemap.xml')."\n" : '';
        }

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** @return Collection<int, array{loc: string, lastmod: string|null, changefreq: string, priority: string}> */
    private function entries(): Collection
    {
        $entry = fn (string $path, ?CarbonInterface $updated, string $freq, string $priority): array => [
            'loc' => WebsiteSeo::canonicalFor($path),
            'lastmod' => $updated?->toDateString(),
            'changefreq' => $freq,
            'priority' => $priority,
        ];

        $pages = Page::query()->live()->where('visibility', 'public')->with('seo')->get()
            ->reject(fn (Page $p) => $p->seo?->noindex === true)
            ->map(fn (Page $p) => $entry($p->system_key === 'home' ? '/' : (string) $p->slug, $p->updated_at, $p->system_key === 'home' ? 'daily' : 'weekly', $p->system_key === 'home' ? '1.0' : '0.8'));

        $posts = BlogPost::query()->live()->with('seo')->get()
            ->reject(fn (BlogPost $p) => $p->seo?->noindex === true)
            ->map(fn (BlogPost $p) => $entry("blog/{$p->slug}", $p->updated_at, 'monthly', '0.6'));

        $universities = University::query()->where('is_active', true)->with('seo')->get()
            ->reject(fn (University $u) => $u->seo?->noindex === true)
            ->map(fn (University $u) => $entry("universities/{$u->slug}", $u->updated_at, 'monthly', '0.6'));

        $assessments = SkillAssessment::query()->published()->get()
            ->map(fn (SkillAssessment $a) => $entry("skill-migration/{$a->slug}", $a->updated_at, 'monthly', '0.5'));

        $extra = collect(preg_split('/\R/', (string) WebsiteSeo::setting('sitemap_extra', '')) ?: [])
            ->map(fn ($l) => trim((string) $l))->filter()
            ->map(fn (string $l) => preg_match('#^https?://#i', $l)
                ? ['loc' => $l, 'lastmod' => null, 'changefreq' => 'monthly', 'priority' => '0.5']
                : $entry($l, null, 'monthly', '0.5'));

        return $pages->concat($posts)->concat($universities)->concat($assessments)->concat($extra)->unique('loc')->values();
    }
}
