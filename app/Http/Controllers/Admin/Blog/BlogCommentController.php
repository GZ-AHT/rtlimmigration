<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Models\BlogComment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §4 Blog Comments: moderation queue. Readers' comments are shown only after approval. */
class BlogCommentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('blog.view');

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(CommentStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? CommentStatus::Pending->value;

        $comments = BlogComment::query()
            ->with('post:id,title,slug')
            ->where('status', $status)
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $q) => $q->where('body', 'like', '%'.addcslashes($s, '%_\\').'%')->orWhere('email', 'like', '%'.addcslashes($s, '%_\\').'%')))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (BlogComment $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'body' => $c->body,
                'post' => ['id' => $c->post->id, 'title' => $c->post->title],
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'status_tone' => $c->status->tone(),
                'ip_address' => $c->ip_address,
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/blog/Comments', [
            'comments' => $comments,
            'filters' => ['status' => $status, 'search' => $filters['search'] ?? ''],
            'counts' => BlogComment::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'statuses' => CommentStatus::options(),
            'canModerate' => $request->user()->can('blog.publish'),
        ]);
    }

    public function update(Request $request, BlogComment $comment): RedirectResponse
    {
        Gate::authorize('blog.publish');

        $data = $request->validate(['status' => ['required', Rule::enum(CommentStatus::class)]]);
        $comment->update(['status' => $data['status']]);

        $this->toast(['type' => 'success', 'message' => __('Comment marked :status.', ['status' => strtolower(CommentStatus::from($data['status'])->label())])]);

        return back();
    }

    public function destroy(BlogComment $comment): RedirectResponse
    {
        Gate::authorize('blog.publish');

        $comment->delete();
        $this->toast(['type' => 'success', 'message' => __('Comment deleted.')]);

        return back();
    }
}
