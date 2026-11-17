<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Http\Controllers\Controller;
use App\Models\AuthorProfile;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §4 Author Management: the public byline (name, title, bio, photo) of staff who write posts. */
class AuthorController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('blog.view');

        $authors = User::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('name', '!=', 'student'))
            ->withCount('blogPosts')
            ->with('authorProfile.avatar')
            ->orderByDesc('blog_posts_count')->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'posts' => $u->blog_posts_count,
                'display_name' => $u->authorProfile->display_name ?? '',
                'job_title' => $u->authorProfile->job_title ?? '',
                'bio' => $u->authorProfile->bio ?? '',
                'avatar' => $u->authorProfile?->avatar?->present(),
            ]);

        return Inertia::render('admin/blog/Authors', [
            'authors' => $authors,
            'canManage' => $request->user()->can('blog.publish'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        // Editors manage every byline; anyone may edit their own.
        abort_unless($request->user()->can('blog.publish') || ($request->user()->is($user) && $request->user()->can('blog.manage')), 403);

        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
        ]);

        AuthorProfile::updateOrCreate(['user_id' => $user->id], $data);
        $this->toast(['type' => 'success', 'message' => __('Author profile saved.')]);

        return back();
    }
}
