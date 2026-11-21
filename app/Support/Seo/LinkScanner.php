<?php

namespace App\Support\Seo;

use App\Models\BlogPost;
use App\Models\BrokenLink;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Finds links on the live website that no longer load (REQ §22.3). It reads
 * page sections, blog posts and menu items, checks each distinct address once
 * and replaces the stored results. Run by `php artisan seo:check-links`.
 */
class LinkScanner
{
    /** Distinct addresses checked per scan, so a scan can't run away. */
    public const LIMIT = 400;

    /** @return int how many broken links were found */
    public function run(): int
    {
        $found = $this->collect();
        $results = [];
        $now = now();

        foreach (array_slice(array_keys($found), 0, self::LIMIT) as $target) {
            [$status, $error] = $this->check($target);

            if ($status === null || $status >= 400) {
                $results[$target] = [$status, $error];
            }
        }

        BrokenLink::query()->delete();

        foreach ($results as $target => [$status, $error]) {
            foreach ($found[$target] as $source) {
                BrokenLink::create([...$source, 'target_url' => $target, 'status_code' => $status, 'error' => $error, 'checked_at' => $now]);
            }
        }

        return BrokenLink::query()->count();
    }

    /** @return array{0: int|null, 1: string|null} */
    public function check(string $target): array
    {
        $url = str_starts_with($target, '/') ? rtrim((string) config('app.url'), '/').$target : $target;

        try {
            $response = Http::timeout(8)->withUserAgent('RTL-LinkChecker/1.0')->head($url);

            if (in_array($response->status(), [403, 405, 501], true)) {
                $response = Http::timeout(8)->withUserAgent('RTL-LinkChecker/1.0')->get($url);
            }

            // 403 / 429 from other sites usually means "no bots", not "gone".
            return [$response->status() === 429 ? null : $response->status(), null];
        } catch (Throwable $e) {
            return [null, str($e->getMessage())->before(' for ')->limit(120)->toString()];
        }
    }

    /** @return array<string, list<array<string, mixed>>> target => the places that link to it */
    public function collect(): array
    {
        $found = [];
        $add = function (string $target, string $type, ?int $id, string $label, ?string $sourceUrl) use (&$found): void {
            $target = trim(html_entity_decode($target));

            if (! preg_match('~^(/(?!/)|https?://)~i', $target) || str_starts_with($target, '/build/') || str_starts_with($target, '/storage/')) {
                return;
            }

            $found[$target][] = ['source_type' => $type, 'source_id' => $id, 'source_label' => $label, 'source_url' => $sourceUrl];
        };

        Page::query()->live()->get()->each(function (Page $page) use ($add) {
            $path = $page->system_key === 'home' ? '/' : '/'.$page->slug;
            $sections = $page->sections;
            array_walk_recursive($sections, function ($value, $key) use ($add, $page, $path) {
                if (is_string($value) && in_array($key, ['url', 'href'], true)) {
                    $add($value, 'page', $page->id, $page->title, $path);
                } elseif (is_string($value) && str_contains($value, 'href=')) {
                    preg_match_all('~href=["\']([^"\']+)["\']~i', $value, $m);
                    foreach ($m[1] as $href) {
                        $add($href, 'page', $page->id, $page->title, $path);
                    }
                }
            });
        });

        BlogPost::query()->live()->get(['id', 'title', 'slug', 'content'])->each(function (BlogPost $post) use ($add) {
            preg_match_all('~href=["\']([^"\']+)["\']~i', $post->content, $m);
            foreach ($m[1] as $href) {
                $add($href, 'post', $post->id, $post->title, "/blog/{$post->slug}");
            }
        });

        MenuItem::query()->where('is_active', true)->whereNotNull('url')->get()->each(
            fn (MenuItem $i) => $add((string) $i->url, 'menu', $i->id, "Menu: {$i->label}", null),
        );

        return $found;
    }
}
