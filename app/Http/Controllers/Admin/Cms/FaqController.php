<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Support\Content\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §21.8 FAQ page (categories, search) and §21.7 homepage FAQ section. */
class FaqController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('cms.manage');

        return Inertia::render('admin/cms/Faqs', [
            'faqs' => Faq::query()->with('category:id,name')->orderBy('sort_order')->orderBy('id')->get()->map(fn (Faq $f) => [
                'id' => $f->id,
                'question' => $f->question,
                'answer' => $f->answer,
                'faq_category_id' => $f->faq_category_id ? (string) $f->faq_category_id : '',
                'category' => $f->category?->name,
                'is_active' => $f->is_active,
                'show_on_home' => $f->show_on_home,
            ]),
            'categories' => FaqCategory::query()->withCount('faqs')->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (FaqCategory $c) => ['id' => $c->id, 'name' => $c->name, 'faqs_count' => $c->faqs_count]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('cms.manage');

        Faq::create([...$this->validated($request), 'sort_order' => (int) Faq::max('sort_order') + 10]);
        $this->toast(['type' => 'success', 'message' => __('Question added.')]);

        return back();
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        Gate::authorize('cms.manage');

        $faq->update($this->validated($request));
        $this->toast(['type' => 'success', 'message' => __('Question saved.')]);

        return back();
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        Gate::authorize('cms.manage');

        $faq->delete();
        $this->toast(['type' => 'success', 'message' => __('Question deleted.')]);

        return back();
    }

    public function reorder(Request $request): RedirectResponse
    {
        Gate::authorize('cms.manage');

        $order = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']])['order'];
        DB::transaction(function () use ($order) {
            foreach (array_values($order) as $i => $id) {
                Faq::whereKey($id)->update(['sort_order' => ($i + 1) * 10]);
            }
        });

        return back();
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        Gate::authorize('cms.manage');

        $name = $request->validate(['name' => ['required', 'string', 'max:80']])['name'];
        FaqCategory::create(['name' => $name, 'slug' => Slug::unique(FaqCategory::class, $name, max: 90), 'sort_order' => (int) FaqCategory::max('sort_order') + 10]);
        $this->toast(['type' => 'success', 'message' => __('Category added.')]);

        return back();
    }

    public function destroyCategory(FaqCategory $category): RedirectResponse
    {
        Gate::authorize('cms.manage');

        $category->delete();
        $this->toast(['type' => 'success', 'message' => __('Category deleted. Its questions are now uncategorised.')]);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:300'],
            'answer' => ['required', 'string', 'max:5000'],
            'faq_category_id' => ['nullable', 'integer', Rule::exists(FaqCategory::class, 'id')],
            'is_active' => ['boolean'],
            'show_on_home' => ['boolean'],
        ]);

        return [...$data, 'is_active' => (bool) ($data['is_active'] ?? true), 'show_on_home' => (bool) ($data['show_on_home'] ?? false)];
    }
}
