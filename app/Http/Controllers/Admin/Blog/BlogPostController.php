<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Enums\PublishStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Media;
use App\Models\User;
use App\Support\Content\RichText;
use App\Support\Content\SeoRules;
use App\Support\Content\Slug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ §4: All / Create / Edit blogs, Featured, Drafts, Published,
 * Scheduled Publishing, Featured Image, SEO. Writers without blog.publish
 * save drafts; an editor publishes them.
 */
class BlogPostController extends Controller
{
    public const STATES = ['published', 'scheduled', 'draft', 'featured', 'trash'];

    public function index(Request $request): Response
    {
        Gate::authorize('blog.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', Rule::in(self::STATES)],
            'category' => ['nullable', 'integer'],
            'author' => ['nullable', 'integer'],
        ]);

        $base = fn () => BlogPost::query()
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where('title', 'like', '%'.addcslashes($s, '%_\\').'%'))
            ->when($filters['category'] ?? null, fn (Builder $q, int|string $c) => $q->where('blog_category_id', $c))
            ->when($filters['author'] ?? null, fn (Builder $q, int|string $a) => $q->where('author_id', $a));

        $posts = self::state($base(), $filters['state'] ?? null)
            ->with(['category:id,name', 'author:id,name', 'featuredImage'])
            ->withCount(['comments as pending_comments' => fn (Builder $q) => $q->where('status', 'pending')])
            ->latest('updated_at')->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BlogPost $p) => self::row($p));

        $counts = collect(self::STATES)->mapWithKeys(fn (string $s) => [$s => self::state($base(), $s)->count()])->all();

        return Inertia::render('admin/blog/Posts', [
            'posts' => $posts,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'state' => $filters['state'] ?? '',
                'category' => isset($filters['category']) ? (string) $filters['category'] : '',
                'author' => isset($filters['author']) ? (string) $filters['author'] : '',
            ],
            'counts' => [...$counts, 'all' => $base()->count()],
            'options' => [
                'categories' => BlogCategory::query()->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->all(),
                'authors' => $this->authorOptions(),
            ],
            'can' => ['manage' => $request->user()->can('blog.manage'), 'publish' => $request->user()->can('blog.publish')],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('blog.manage');

        return $this->form($request, null);
    }

    public function edit(Request $request, BlogPost $post): Response
    {
        Gate::authorize('blog.manage');

        return $this->form($request, $post->load(['tags', 'featuredImage', 'seo.ogImage']));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('blog.manage');

        $post = DB::transaction(function () use ($request) {
            $post = new BlogPost;
            $this->fill($request, $post);

            return $post;
        });

        activity('blog')->causedBy($request->user())->performedOn($post)->log('Blog post created');
        $this->toast(['type' => 'success', 'message' => __('Post saved.')]);

        return redirect()->route('admin.blog.posts.edit', $post);
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        Gate::authorize('blog.manage');

        DB::transaction(fn () => $this->fill($request, $post));

        activity('blog')->causedBy($request->user())->performedOn($post)->log('Blog post updated');
        $this->toast(['type' => 'success', 'message' => __('Post saved.')]);

        return back();
    }

    public function feature(Request $request, BlogPost $post): RedirectResponse
    {
        Gate::authorize('blog.publish');

        $post->update(['is_featured' => ! $post->is_featured]);
        $this->toast(['type' => 'success', 'message' => $post->is_featured ? __('Post featured.') : __('Post no longer featured.')]);

        return back();
    }

    public function destroy(Request $request, BlogPost $post): RedirectResponse
    {
        Gate::authorize('blog.manage');

        $post->delete();
        activity('blog')->causedBy($request->user())->performedOn($post)->log('Blog post moved to trash');
        $this->toast(['type' => 'success', 'message' => __('Post moved to trash.')]);

        return redirect()->route('admin.blog.posts.index');
    }

    public function restore(Request $request, int $post): RedirectResponse
    {
        Gate::authorize('blog.manage');

        $model = BlogPost::onlyTrashed()->findOrFail($post);
        $model->restore();
        $this->toast(['type' => 'success', 'message' => __('Post restored as it was.')]);

        return back();
    }

    /**
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    private static function state(Builder $query, ?string $state): Builder
    {
        return match ($state) {
            'published' => $query->live(),
            'scheduled' => $query->where('status', PublishStatus::Published)->where('published_at', '>', now()),
            'draft' => $query->where('status', PublishStatus::Draft),
            'featured' => $query->where('is_featured', true),
            'trash' => $query->onlyTrashed(),
            default => $query,
        };
    }

    /** @return array<string, mixed> */
    public static function row(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'category' => $post->category?->name,
            'author' => $post->author?->name,
            'image' => $post->featuredImage?->url(),
            'state' => $post->status->state($post->published_at),
            'is_featured' => $post->is_featured,
            'published_at' => $post->published_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
            'pending_comments' => (int) ($post->getAttribute('pending_comments') ?? 0),
            'views' => $post->views,
            'trashed' => $post->trashed(),
        ];
    }

    private function form(Request $request, ?BlogPost $post): Response
    {
        return Inertia::render('admin/blog/PostForm', [
            'post' => $post ? [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt ?? '',
                'content' => $post->content,
                'featured_image' => $post->featuredImage?->present(),
                'blog_category_id' => $post->blog_category_id ? (string) $post->blog_category_id : '',
                'author_id' => $post->author_id ? (string) $post->author_id : '',
                'tags' => $post->tags->pluck('name')->all(),
                'status' => $post->status->value,
                'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
                'is_featured' => $post->is_featured,
                'allow_comments' => $post->allow_comments,
                'state' => $post->status->state($post->published_at),
                'seo' => $post->seoForForm(),
            ] : null,
            'options' => [
                'categories' => BlogCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->all(),
                'tags' => BlogTag::query()->orderBy('name')->pluck('name')->all(),
                'authors' => $this->authorOptions(),
            ],
            'can' => ['publish' => $request->user()->can('blog.publish')],
            'defaultAuthor' => (string) $request->user()->id,
        ]);
    }

    private function fill(Request $request, BlogPost $post): void
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'excerpt' => ['nullable', 'string', 'max:400'],
            'content' => ['required', 'string', 'max:200000'],
            'featured_image_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
            'blog_category_id' => ['nullable', 'integer', Rule::exists(BlogCategory::class, 'id')],
            'author_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'tags' => ['array', 'max:15'],
            'tags.*' => ['string', 'max:60'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['boolean'],
            'allow_comments' => ['boolean'],
            ...SeoRules::rules(),
        ], ['slug.regex' => __('Use lowercase letters, numbers and dashes only.')]);

        $content = RichText::clean($data['content']);
        $words = str_word_count(RichText::text($content));

        $post->fill([
            'title' => $data['title'],
            'slug' => Slug::unique(BlogPost::class, filled($data['slug'] ?? null) ? $data['slug'] : $data['title'], $post->id),
            'excerpt' => filled($data['excerpt'] ?? null) ? $data['excerpt'] : RichText::text($content, 200),
            'content' => $content,
            'featured_image_id' => $data['featured_image_id'] ?? null,
            'blog_category_id' => $data['blog_category_id'] ?? null,
            'author_id' => $data['author_id'] ?? $post->author_id ?? $request->user()->id,
            'allow_comments' => (bool) ($data['allow_comments'] ?? true),
            'reading_minutes' => max(1, (int) ceil($words / 200)),
        ]);

        // Publishing, scheduling and featuring need blog.publish; writers keep the current state.
        if ($request->user()->can('blog.publish')) {
            $status = PublishStatus::from($data['status']);
            $post->status = $status;
            $post->published_at = $status === PublishStatus::Published
                ? (isset($data['published_at']) ? Carbon::parse($data['published_at']) : ($post->published_at ?? now()))
                : (isset($data['published_at']) ? Carbon::parse($data['published_at']) : null);
            $post->is_featured = (bool) ($data['is_featured'] ?? false);
        } elseif (! $post->exists) {
            $post->status = PublishStatus::Draft;
        }

        $post->save();

        $tagIds = [];
        foreach ($data['tags'] ?? [] as $name) {
            $name = trim((string) $name);
            $slug = Str::slug($name);

            if ($name !== '' && $slug !== '' && ! isset($tagIds[$slug])) {
                $tagIds[$slug] = BlogTag::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
            }
        }
        $post->tags()->sync(array_values($tagIds));

        $post->saveSeo($data['seo'] ?? null);
    }

    /** @return list<array{value: string, label: string}> */
    private function authorOptions(): array
    {
        return array_values(User::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('name', '!=', Role::Student->value))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $u) => ['value' => (string) $u->id, 'label' => $u->name])->all());
    }
}
