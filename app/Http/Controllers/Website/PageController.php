<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\User;
use App\Support\Cms\SectionHydrator;
use App\Support\Cms\WebsiteSeo;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public website pages built in the CMS (REQ §5, §21.7, §21.8): the
 * homepage, built-in pages (about, contact, FAQ, blog, reviews…) and any
 * custom page. Drafts and scheduled pages are 404 for visitors; people who
 * can edit pages see them with a preview banner.
 */
class PageController extends Controller
{
    public function home(Request $request): Response|RedirectResponse
    {
        return $this->render($request, Page::query()->where('system_key', 'home')->first());
    }

    public function contact(Request $request): Response|RedirectResponse
    {
        return $this->render($request, Page::query()->where('system_key', 'contact')->first());
    }

    public function show(Request $request, string $slug): Response|RedirectResponse
    {
        $page = Page::query()->where('slug', $slug)->first();

        // The homepage lives at "/", never at its slug.
        if ($page?->system_key === 'home') {
            return redirect('/', 301);
        }

        return $this->render($request, $page);
    }

    private function render(Request $request, ?Page $page): Response|RedirectResponse
    {
        abort_if($page === null, 404);

        /** @var User|null $user */
        $user = $request->user();
        $preview = ! $page->isLive();

        abort_if($preview && ! $user?->can('pages.manage'), 404);

        if ($page->visibility === 'members' && $user === null) {
            return redirect()->guest(route('login'));
        }

        $page->load('seo.ogImage');
        $sections = app(SectionHydrator::class)->hydrate($page);
        $first = $sections[0]['data'] ?? [];
        $image = collect($sections)->pluck('data.image.url')->filter()->first();

        return Inertia::render('website/Page', [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'system_key' => $page->system_key,
                'preview' => $preview ? ($page->published_at?->isFuture() ? 'scheduled' : 'draft') : null,
                'edit_url' => $user?->can('pages.manage') ? route('admin.pages.edit', $page) : null,
                // An editor previewing the other homepage banner design (?banner=…).
                'banner_preview' => in_array($request->query('banner'), SectionHydrator::BANNERS, true) && $user?->can('pages.manage') ? $request->query('banner') : null,
                'custom_css' => (string) $page->custom_css,
                'custom_js' => (string) $page->custom_js,
            ],
            'sections' => $sections,
            'seo' => [
                ...WebsiteSeo::make(
                    $page->system_key === 'home' ? (string) app(SettingsRepository::class)->get('general.site_name', config('app.name')) : $page->title,
                    is_string($first['description'] ?? null) ? $first['description'] : null,
                    $page->seo,
                    noindex: $preview || $page->visibility !== 'public' || $request->has('banner'),
                ),
                'image' => $page->seo?->ogImage?->url() ?? $image,
            ],
        ]);
    }
}
