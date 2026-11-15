<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §26 document_categories: which documents students are asked for. */
class DocumentCategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('settings.manage');

        return Inertia::render('admin/document-categories/Index', [
            'categories' => DocumentCategory::query()->withCount('documents')->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (DocumentCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'description' => $c->description,
                    'is_required' => $c->is_required,
                    'is_active' => $c->is_active,
                    'documents_count' => $c->documents_count,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $data = $this->validated($request);
        DocumentCategory::create([...$data, 'sort_order' => (int) DocumentCategory::max('sort_order') + 10]);

        $this->toast(['type' => 'success', 'message' => __('Category added.')]);

        return back();
    }

    public function update(Request $request, DocumentCategory $category): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $category->update($this->validated($request, $category));

        $this->toast(['type' => 'success', 'message' => __('Category updated.')]);

        return back();
    }

    public function destroy(DocumentCategory $category): RedirectResponse
    {
        Gate::authorize('settings.manage');

        if ($category->documents()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['category' => __('Documents use this category. Deactivate it instead.')]);
        }

        $category->delete();

        $this->toast(['type' => 'success', 'message' => __('Category deleted.')]);

        return back();
    }

    /** @return array{name: string, slug: string, description: string|null, is_required: bool, is_active: bool} */
    private function validated(Request $request, ?DocumentCategory $category = null): array
    {
        $request->merge(['slug' => Str::slug((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', Rule::unique(DocumentCategory::class, 'slug')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ], ['slug.unique' => __('A category with this name already exists.')]);

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_required' => (bool) $data['is_required'],
            'is_active' => (bool) $data['is_active'],
        ];
    }
}
