<?php

namespace App\Http\Controllers\Admin\SkillAssessments;

use App\Http\Controllers\Controller;
use App\Models\SkillAssessmentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Question categories (steps in the assessment, scored separately in the result). */
class CategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('assessments.manage');
        SkillAssessmentCategory::create($this->validated($request));
        $this->toast(['type' => 'success', 'message' => __('Category added.')]);

        return back();
    }

    public function update(Request $request, SkillAssessmentCategory $category): RedirectResponse
    {
        Gate::authorize('assessments.manage');
        $category->update($this->validated($request));
        $this->toast(['type' => 'success', 'message' => __('Category saved.')]);

        return back();
    }

    public function destroy(SkillAssessmentCategory $category): RedirectResponse
    {
        Gate::authorize('assessments.manage');
        $category->delete();
        $this->toast(['type' => 'success', 'message' => __('Category deleted. Its questions are now under “General”.')]);

        return back();
    }

    /** @return array{name: string, sort_order: int} */
    private function validated(Request $request): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'sort_order' => ['nullable', 'integer', 'between:0,1000']]);

        return ['name' => $data['name'], 'sort_order' => (int) ($data['sort_order'] ?? 0)];
    }
}
