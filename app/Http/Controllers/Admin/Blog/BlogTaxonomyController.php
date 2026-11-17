<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogTag;
use App\Support\Content\Slug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §4 Blog Categories and Blog Tags (one screen each, same controller). */
class BlogTaxonomyController extends Controller
{
    public function index(Request $request, string $kind): Response
    {
        Gate::authorize('blog.view');

        $items = $kind === 'categories'
            ? BlogCategory::query()->withCount('posts')->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (BlogCategory $c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'description' => $c->description, 'is_active' => $c->is_active, 'posts_count' => $c->posts_count])
            : BlogTag::query()->withCount('posts')->orderBy('name')->get()
                ->map(fn (BlogTag $t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug, 'description' => null, 'is_active' => true, 'posts_count' => $t->posts_count]);

        return Inertia::render('admin/blog/Taxonomy', [
            'kind' => $kind,
            'items' => $items,
            'canManage' => $request->user()->can('blog.manage'),
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        Gate::authorize('blog.manage');

        $data = $this->validated($request);
        $model = $this->model($kind);
        $model::create([...$this->attributes($kind, $data), 'slug' => Slug::unique($model, $data['name'], max: 90)]);

        $this->toast(['type' => 'success', 'message' => __('Added.')]);

        return back();
    }

    public function update(Request $request, string $kind, int $id): RedirectResponse
    {
        Gate::authorize('blog.manage');

        $model = $this->model($kind);
        $item = $model::query()->findOrFail($id);
        $data = $this->validated($request);
        $item->update([...$this->attributes($kind, $data), 'slug' => Slug::unique($model, $data['name'], $item->getKey(), max: 90)]);

        $this->toast(['type' => 'success', 'message' => __('Saved.')]);

        return back();
    }

    public function destroy(string $kind, int $id): RedirectResponse
    {
        Gate::authorize('blog.manage');

        // Posts keep existing: a deleted category leaves them uncategorised; a deleted tag is just removed.
        $this->model($kind)::query()->findOrFail($id)->delete();
        $this->toast(['type' => 'success', 'message' => __('Deleted.')]);

        return back();
    }

    /** @return class-string<Model> */
    private function model(string $kind): string
    {
        return $kind === 'categories' ? BlogCategory::class : BlogTag::class;
    }

    /** @return array{name: string, description?: string|null, is_active?: bool} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @param  array{name: string, description?: string|null, is_active?: bool}  $data
     * @return array<string, mixed>
     */
    private function attributes(string $kind, array $data): array
    {
        return $kind === 'categories'
            ? ['name' => $data['name'], 'description' => $data['description'] ?? null, 'is_active' => (bool) ($data['is_active'] ?? true)]
            : ['name' => $data['name']];
    }
}
