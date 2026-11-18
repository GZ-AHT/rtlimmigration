<?php

namespace App\Http\Controllers\Website;

use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use App\Support\Cms\SectionHydrator;
use App\Support\Cms\WebsiteSeo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Blog article page (REQ §4, §21.8; not in Figma, derived from the blog
 * cards and About page typography). The listing is the "blog" CMS page.
 * Comments are held for moderation (REQ §4 "Comments Management").
 */
class BlogController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        $post = BlogPost::query()->where('slug', $slug)
            ->with(['featuredImage', 'category:id,name,slug', 'tags:id,name,slug', 'author.authorProfile.avatar', 'seo.ogImage'])
            ->firstOrFail();

        $preview = ! BlogPost::query()->whereKey($post->id)->live()->exists();
        abort_if($preview && ! $user?->can('blog.manage'), 404);

        // Count one view per visitor session.
        if (! $preview && ! $request->session()->has("viewed_post.{$post->id}")) {
            $post->increment('views');
            $request->session()->put("viewed_post.{$post->id}", true);
        }

        $profile = $post->author?->authorProfile;

        return Inertia::render('website/BlogPost', [
            'post' => [
                ...SectionHydrator::post($post),
                'content' => $post->content,
                'tags' => $post->tags->map(fn (BlogTag $t) => $t->name)->values(),
                'author' => $post->author ? [
                    'name' => $profile?->display_name ?: $post->author->name,
                    'job_title' => $profile?->job_title,
                    'bio' => $profile?->bio,
                    'avatar' => SectionHydrator::image($profile?->avatar),
                ] : null,
                'allow_comments' => $post->allow_comments,
                'preview' => $preview,
            ],
            'comments' => $post->comments()->where('status', CommentStatus::Approved)->whereNull('parent_id')
                ->with(['replies' => fn ($q) => $q->where('status', CommentStatus::Approved)->oldest()])
                ->oldest()->limit(100)->get()
                ->map(fn (BlogComment $c) => self::presentComment($c))->values(),
            'related' => $this->related($post),
            'seo' => WebsiteSeo::make($post->title, $post->excerpt, $post->seo, $post->featuredImage, 'article', $preview),
        ]);
    }

    public function comment(Request $request, string $slug): RedirectResponse
    {
        $post = BlogPost::query()->live()->where('slug', $slug)->firstOrFail();
        abort_unless($post->allow_comments, 403);

        // Honeypot: real visitors never see or fill this field.
        if ($request->filled('website')) {
            return back();
        }

        /** @var User|null $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => [$user ? 'nullable' : 'required', 'string', 'max:80'],
            'email' => [$user ? 'nullable' : 'required', 'email', 'max:255'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parent = isset($data['parent_id'])
            ? $post->comments()->whereKey($data['parent_id'])->where('status', CommentStatus::Approved)->whereNull('parent_id')->first()
            : null;

        $post->comments()->create([
            'parent_id' => $parent?->id,
            'user_id' => $user?->id,
            'name' => $user ? $user->name : $data['name'],
            'email' => $user ? $user->email : $data['email'],
            'body' => strip_tags($data['body']),
            'status' => CommentStatus::Pending,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        $this->toast(['type' => 'success', 'message' => __('Thank you. Your comment will appear once it has been approved.')]);

        return back();
    }

    /**
     * Up to three more articles: same category first, then the latest.
     *
     * @return list<array<string, mixed>>
     */
    private function related(BlogPost $post): array
    {
        $base = fn () => BlogPost::query()->live()->whereKeyNot($post->id)->with(['featuredImage', 'category:id,name,slug', 'tags:id,name'])->latest('published_at');

        $posts = $post->blog_category_id ? $base()->where('blog_category_id', $post->blog_category_id)->limit(3)->get() : collect();

        if ($posts->count() < 3) {
            $posts = $posts->concat($base()->whereKeyNot($posts->pluck('id')->all())->limit(3 - $posts->count())->get());
        }

        return array_values($posts->map(fn (BlogPost $p) => SectionHydrator::post($p))->all());
    }

    /** @return array<string, mixed> */
    private static function presentComment(BlogComment $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'body' => $c->body,
            'date' => $c->created_at?->format('d M Y'),
            'replies' => $c->relationLoaded('replies') ? $c->replies->map(fn (BlogComment $r) => self::presentComment($r))->values() : [],
        ];
    }
}
