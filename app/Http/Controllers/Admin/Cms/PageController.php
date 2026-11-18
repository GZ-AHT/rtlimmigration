<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Enums\PublishStatus;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageRevision;
use App\Support\Cms\SectionSanitizer;
use App\Support\Cms\SectionTypes;
use App\Support\Content\SeoRules;
use App\Support\Content\Slug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ §5 Page Management: pages built from sections (roadmap Q7), status,
 * scheduling, visibility, categories, SEO, revision history and custom
 * CSS/JS (Super Admin only, audit-logged: roadmap Q16). The homepage is the
 * "home" system page, so REQ §21.7's section manager is this same editor.
 */
class PageController extends Controller
{
    /** Revisions kept per page; older ones are pruned. */
    public const MAX_REVISIONS = 30;

    public function index(Request $request): Response
    {
        Gate::authorize('pages.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:60'],
            'trash' => ['nullable', 'boolean'],
        ]);

        $pages = Page::query()
            ->when($filters['trash'] ?? false, fn (Builder $q) => $q->onlyTrashed())
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $q) => $q->where('title', 'like', '%'.addcslashes($s, '%_\\').'%')->orWhere('slug', 'like', '%'.addcslashes($s, '%_\\').'%')))
            ->when($filters['category'] ?? null, fn (Builder $q, string $c) => $q->where('category', $c))
            ->orderByRaw('system_key is null')->orderBy('title')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Page $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'path' => $p->system_key === 'home' ? '/' : '/'.$p->slug,
                'system' => $p->system_key !== null,
                'category' => $p->category,
                'state' => $p->status->state($p->published_at),
                'visibility' => Page::VISIBILITY[$p->visibility] ?? $p->visibility,
                'sections' => count($p->sections ?? []),
                'updated_at' => $p->updated_at?->toIso8601String(),
                'trashed' => $p->trashed(),
            ]);

        return Inertia::render('admin/cms/Pages', [
            'pages' => $pages,
            'filters' => ['search' => $filters['search'] ?? '', 'category' => $filters['category'] ?? '', 'trash' => (bool) ($filters['trash'] ?? false)],
            'categories' => Page::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'can' => ['manage' => $request->user()->can('pages.manage')],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('pages.manage');

        return $this->form($request, null);
    }

    public function edit(Request $request, Page $page): Response
    {
        Gate::authorize('pages.view');

        return $this->form($request, $page->load('seo.ogImage'));
    }

    public function store(Request $request, SectionSanitizer $sanitizer): RedirectResponse
    {
        Gate::authorize('pages.manage');

        $page = DB::transaction(function () use ($request, $sanitizer) {
            $page = new Page(['created_by' => $request->user()->id]);
            $this->fill($request, $page, $sanitizer);

            return $page;
        });

        activity('pages')->causedBy($request->user())->performedOn($page)->log('Page created');
        $this->toast(['type' => 'success', 'message' => __('Page saved.')]);

        return redirect()->route('admin.pages.edit', $page);
    }

    public function update(Request $request, Page $page, SectionSanitizer $sanitizer): RedirectResponse
    {
        Gate::authorize('pages.manage');

        DB::transaction(function () use ($request, $page, $sanitizer) {
            // Keep the version being replaced (REQ §5 Page Revision History).
            $page->revisions()->create([
                'title' => $page->title,
                'sections' => $page->sections ?? [],
                'custom_css' => $page->custom_css,
                'custom_js' => $page->custom_js,
                'created_by' => $page->updated_by ?? $page->created_by,
                'created_at' => $page->updated_at ?? now(),
            ]);

            $this->fill($request, $page, $sanitizer);

            $keep = $page->revisions()->latest('id')->limit(self::MAX_REVISIONS)->pluck('id');
            $page->revisions()->whereNotIn('id', $keep)->delete();
        });

        activity('pages')->causedBy($request->user())->performedOn($page)->log('Page updated');
        $this->toast(['type' => 'success', 'message' => __('Page saved.')]);

        return back();
    }

    public function restoreRevision(Request $request, Page $page, PageRevision $revision, SectionSanitizer $sanitizer): RedirectResponse
    {
        Gate::authorize('pages.manage');
        abort_unless($revision->page_id === $page->id, 404);

        DB::transaction(function () use ($request, $page, $revision, $sanitizer) {
            $page->revisions()->create([
                'title' => $page->title, 'sections' => $page->sections ?? [], 'custom_css' => $page->custom_css, 'custom_js' => $page->custom_js,
                'created_by' => $page->updated_by, 'created_at' => $page->updated_at ?? now(),
            ]);

            $page->update([
                'title' => $revision->title,
                // Re-cleaned: media may have been deleted since.
                'sections' => $sanitizer->clean($revision->sections),
                // Custom code only comes back for someone allowed to set it.
                ...($request->user()->can('cms.custom-code') ? ['custom_css' => $revision->custom_css, 'custom_js' => $revision->custom_js] : []),
                'updated_by' => $request->user()->id,
            ]);
        });

        activity('pages')->causedBy($request->user())->performedOn($page)->withProperties(['revision' => $revision->id])->log('Page revision restored');
        $this->toast(['type' => 'success', 'message' => __('Earlier version restored. The replaced version was kept in the history.')]);

        return back();
    }

    public function destroy(Request $request, Page $page): RedirectResponse
    {
        Gate::authorize('pages.manage');

        if ($page->system_key !== null) {
            throw ValidationException::withMessages(['page' => __('Built-in pages can be hidden or unpublished, but not deleted.')]);
        }

        $page->delete();
        activity('pages')->causedBy($request->user())->performedOn($page)->log('Page moved to trash');
        $this->toast(['type' => 'success', 'message' => __('Page moved to trash.')]);

        return redirect()->route('admin.pages.index');
    }

    public function restore(int $page): RedirectResponse
    {
        Gate::authorize('pages.manage');

        Page::onlyTrashed()->findOrFail($page)->restore();
        $this->toast(['type' => 'success', 'message' => __('Page restored.')]);

        return back();
    }

    private function form(Request $request, ?Page $page): Response
    {
        $sections = $page !== null ? $page->sections : [];

        return Inertia::render('admin/cms/PageEditor', [
            'page' => $page ? [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'path' => $page->system_key === 'home' ? '/' : '/'.$page->slug,
                'system_key' => $page->system_key,
                'category' => $page->category ?? '',
                'status' => $page->status->value,
                'published_at' => $page->published_at?->format('Y-m-d\TH:i'),
                'visibility' => $page->visibility,
                'sections' => $sections,
                'custom_css' => $page->custom_css ?? '',
                'custom_js' => $page->custom_js ?? '',
                'state' => $page->status->state($page->published_at),
                'seo' => $page->seoForForm(),
                'revisions' => $page->revisions()->with('author:id,name')->latest('id')->limit(self::MAX_REVISIONS)->get()->map(fn (PageRevision $r) => [
                    'id' => $r->id, 'title' => $r->title, 'sections' => count($r->sections), 'author' => $r->author?->name, 'created_at' => $r->created_at?->toIso8601String(),
                ]),
            ] : null,
            'sectionTypes' => SectionTypes::all(),
            'sectionDefaults' => collect(SectionTypes::all())->map(fn ($t, string $key) => SectionTypes::defaults($key)),
            'backgrounds' => SectionTypes::BACKGROUNDS,
            'spacing' => SectionTypes::SPACING,
            'visibility' => Page::VISIBILITY,
            'media' => $this->mediaMap($sections),
            'categories' => Page::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'can' => [
                'manage' => $request->user()->can('pages.manage'),
                'publish' => $request->user()->can('pages.publish'),
                'custom_code' => $request->user()->can('cms.custom-code'),
            ],
        ]);
    }

    private function fill(Request $request, Page $page, SectionSanitizer $sanitizer): void
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(self::reservedSlugs())],
            'category' => ['nullable', 'string', 'max:60'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
            'published_at' => ['nullable', 'date'],
            'visibility' => ['required', Rule::in(array_keys(Page::VISIBILITY))],
            'sections' => ['present', 'array'],
            'custom_css' => ['nullable', 'string', 'max:20000'],
            'custom_js' => ['nullable', 'string', 'max:20000'],
            ...SeoRules::rules(),
        ], [
            'slug.regex' => __('Use lowercase letters, numbers and dashes only.'),
            'slug.not_in' => __('This address is used by another part of the website.'),
        ]);

        $user = $request->user();
        $page->fill([
            'title' => $data['title'],
            'category' => filled($data['category'] ?? null) ? trim($data['category']) : null,
            'visibility' => $data['visibility'],
            'sections' => $sanitizer->clean($data['sections']),
            'updated_by' => $user->id,
        ]);

        // Built-in pages keep their address; the website's own routes depend on it.
        if ($page->system_key === null) {
            $page->slug = Slug::unique(Page::class, filled($data['slug'] ?? null) ? $data['slug'] : $data['title'], $page->id);

            if (in_array($page->slug, self::reservedSlugs(), true)) {
                $page->slug .= '-page';
            }
        }

        if ($user->can('pages.publish')) {
            $page->status = PublishStatus::from($data['status']);
            $page->published_at = isset($data['published_at']) ? Carbon::parse($data['published_at']) : ($page->status === PublishStatus::Published ? ($page->published_at ?? now()) : null);
        } elseif (! $page->exists) {
            $page->status = PublishStatus::Draft;
        }

        // Roadmap Q16: custom code is Super Admin only and every change is logged.
        if ($user->can('cms.custom-code')) {
            $css = filled($data['custom_css'] ?? null) ? $data['custom_css'] : null;
            $js = filled($data['custom_js'] ?? null) ? $data['custom_js'] : null;

            if ($css !== $page->custom_css || $js !== $page->custom_js) {
                activity('security')->causedBy($user)->performedOn($page)->withProperties(['css_changed' => $css !== $page->custom_css, 'js_changed' => $js !== $page->custom_js])->log('Page custom code changed');
            }

            // A closing tag would let the CSS break out of its <style> element.
            $page->custom_css = $css !== null ? (string) preg_replace('~</style~i', '', $css) : null;
            $page->custom_js = $js;
        }

        $page->save();
        $page->saveSeo($data['seo'] ?? null);
    }

    /**
     * Addresses owned by the website's own routes (blog, universities, login…).
     *
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        return ['admin', 'counselor', 'accounts', 'portal', 'login', 'logout', 'register', 'forgot-password', 'reset-password', 'two-factor-challenge',
            'user', 'settings', 'blog', 'universities', 'courses', 'find-your-pathway', 'chat', 'contact', 'enquiry', 'newsletter', 'partners', 'storage',
            'email', 'payments', 'invitation', 'notifications', 'search', 'dashboard', 'api', 'up', 'build', 'sitemap', 'robots'];
    }

    /**
     * Images referenced by the sections, so the editor can show them.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function mediaMap(array $sections): array
    {
        return Media::query()->whereKey(SectionTypes::mediaIds($sections))->get()->mapWithKeys(fn (Media $m) => [$m->id => $m->present()])->all();
    }
}
