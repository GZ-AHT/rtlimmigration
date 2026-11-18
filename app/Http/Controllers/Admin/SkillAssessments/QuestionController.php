<?php

namespace App\Http\Controllers\Admin\SkillAssessments;

use App\Http\Controllers\Controller;
use App\Models\SkillAssessment;
use App\Models\SkillAssessmentCategory;
use App\Models\SkillAssessmentQuestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shared question bank: multiple-choice questions, their answers and
 * points, their category, and which countries' assessments use them.
 * Submissions keep their own copy, so editing a question never changes a
 * past result.
 */
class QuestionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('assessments.manage');

        $filters = $request->validate([
            'category' => ['nullable', 'integer'],
            'assessment' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $questions = SkillAssessmentQuestion::query()
            ->with(['category:id,name', 'options', 'assessments:id,country_id', 'assessments.country:id,name'])
            ->when($filters['category'] ?? null, fn (Builder $q, int $c) => $q->where('skill_assessment_category_id', $c))
            ->when($filters['assessment'] ?? null, fn (Builder $q, int $a) => $q->whereHas('assessments', fn (Builder $w) => $w->whereKey($a)))
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where('text', 'like', '%'.addcslashes($s, '%_\\').'%'))
            ->orderBy('skill_assessment_category_id')->orderBy('id')
            ->get();

        return Inertia::render('admin/skill/Questions', [
            'questions' => $questions->map(fn (SkillAssessmentQuestion $q) => [
                'id' => $q->id,
                'text' => $q->text,
                'help' => (string) $q->help,
                'category_id' => $q->skill_assessment_category_id ? (string) $q->skill_assessment_category_id : '',
                'category' => $q->category->name ?? __('General'),
                'is_active' => $q->is_active,
                'options' => $q->options->map(fn ($o) => AssessmentController::option($o))->values(),
                'assessment_ids' => $q->assessments->pluck('id')->map(fn ($id) => (string) $id)->values(),
                'countries' => $q->assessments->map(fn (SkillAssessment $a) => $a->country->name)->sort()->values(),
            ])->values(),
            'categories' => SkillAssessmentCategory::query()->withCount('questions')->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (SkillAssessmentCategory $c) => ['id' => $c->id, 'name' => $c->name, 'sort_order' => $c->sort_order, 'questions' => $c->questions_count]),
            'assessments' => SkillAssessment::query()->with('country:id,name')->get()->sortBy('country.name')
                ->map(fn (SkillAssessment $a) => ['value' => (string) $a->id, 'label' => $a->country->name])->values(),
            'filters' => ['category' => (string) ($filters['category'] ?? ''), 'assessment' => (string) ($filters['assessment'] ?? ''), 'search' => $filters['search'] ?? ''],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('assessments.manage');

        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            $question = SkillAssessmentQuestion::create($this->attributes($data));
            $this->saveOptions($question, $data['options']);
            $this->saveCountries($question, $data['assessment_ids'] ?? []);
        });

        $this->toast(['type' => 'success', 'message' => __('Question added.')]);

        return back();
    }

    public function update(Request $request, SkillAssessmentQuestion $question): RedirectResponse
    {
        Gate::authorize('assessments.manage');

        $data = $this->validated($request);
        DB::transaction(function () use ($question, $data) {
            $question->update($this->attributes($data));
            $this->saveOptions($question, $data['options']);
            $this->saveCountries($question, $data['assessment_ids'] ?? []);
        });

        $this->toast(['type' => 'success', 'message' => __('Question saved.')]);

        return back();
    }

    public function destroy(SkillAssessmentQuestion $question): RedirectResponse
    {
        Gate::authorize('assessments.manage');

        $question->delete();
        $this->toast(['type' => 'success', 'message' => __('Question deleted.')]);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'text' => ['required', 'string', 'max:300'],
            'help' => ['nullable', 'string', 'max:300'],
            'category_id' => ['nullable', 'integer', Rule::exists(SkillAssessmentCategory::class, 'id')],
            'is_active' => ['boolean'],
            'options' => ['required', 'array', 'min:2', 'max:10'],
            'options.*.label' => ['required', 'string', 'max:200'],
            'options.*.points' => ['required', 'integer', 'between:0,100'],
            'assessment_ids' => ['array'],
            'assessment_ids.*' => ['integer', 'distinct', Rule::exists(SkillAssessment::class, 'id')],
        ], ['options.min' => __('Give at least two answers.')]);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'text' => $data['text'],
            'help' => $data['help'] ?? null,
            'skill_assessment_category_id' => $data['category_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /** @param list<array{label: string, points: int}> $options */
    private function saveOptions(SkillAssessmentQuestion $question, array $options): void
    {
        $question->options()->delete();
        foreach ($options as $i => $o) {
            $question->options()->create(['label' => $o['label'], 'points' => $o['points'], 'sort_order' => $i]);
        }
    }

    /**
     * Adds the question to the chosen countries (at the end, weight 1) and removes it from the others.
     *
     * @param  array<int, int|string>  $ids
     */
    private function saveCountries(SkillAssessmentQuestion $question, array $ids): void
    {
        $ids = array_map('intval', $ids);
        $current = array_map('intval', $question->assessments()->pluck('skill_assessments.id')->all());
        $question->assessments()->detach(array_diff($current, $ids));

        foreach (array_diff($ids, $current) as $id) {
            $next = (int) DB::table('skill_assessment_question_links')->where('skill_assessment_id', $id)->max('sort_order') + 1;
            $question->assessments()->attach($id, ['weight' => 1, 'sort_order' => $next]);
        }
    }
}
