<?php

namespace App\Http\Controllers\Admin\SkillAssessments;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\SkillAssessment;
use App\Models\SkillAssessmentBand;
use App\Models\SkillAssessmentCategory;
use App\Models\SkillAssessmentOption;
use App\Models\SkillAssessmentQuestion;
use App\Support\Content\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One skill migration assessment per destination country: its details,
 * passing percentage, recommendations, and which questions from the bank
 * it uses (with a weight each). Publishing needs at least one question.
 */
class AssessmentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('assessments.view');

        $assessments = SkillAssessment::query()->with('country:id,name,iso2,is_active')
            ->withCount(['liveQuestions', 'submissions', 'submissions as completed_count' => fn ($q) => $q->where('status', 'completed')->where('is_preview', false)])
            ->get()->sortBy('country.name')->values();

        return Inertia::render('admin/skill/Assessments', [
            'assessments' => $assessments->map(fn (SkillAssessment $a) => [
                'id' => $a->id,
                'country' => $a->country->name,
                'iso2' => strtolower($a->country->iso2),
                'country_active' => $a->country->is_active,
                'title' => $a->title,
                'slug' => $a->slug,
                'status' => $a->status,
                'pass_percentage' => $a->pass_percentage,
                'questions' => $a->live_questions_count,
                'completed' => $a->completed_count,
                'url' => route('skill-migration.show', $a->slug, false),
            ]),
            // Active countries without an assessment yet.
            'countries' => Country::query()->where('is_active', true)->whereNotIn('id', $assessments->pluck('country_id'))
                ->orderByDesc('is_destination')->orderBy('name')->get(['id', 'name'])
                ->map(fn (Country $c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            'canManage' => $request->user()->can('assessments.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('assessments.manage');

        $data = $request->validate([
            'country_id' => ['required', 'integer', Rule::exists(Country::class, 'id')->where('is_active', true), Rule::unique(SkillAssessment::class)],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(SkillAssessment::class)],
            'title' => ['required', 'string', 'max:160'],
            'pass_percentage' => ['required', 'integer', 'between:1,100'],
        ], ['country_id.unique' => __('This country already has an assessment.'), 'slug.regex' => __('Use lowercase letters, numbers and dashes, e.g. australia or uk.')]);

        $assessment = DB::transaction(function () use ($data, $request) {
            $assessment = SkillAssessment::create([...$data, 'status' => 'draft', 'minutes' => 5, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $pass = $data['pass_percentage'];

            // Starter recommendations, ready to edit.
            foreach ([
                [min(100, $pass + 15), __('Strong profile'), __('Your answers show a strong profile for this destination. Book a consultation to plan your application.')],
                [$pass, __('Promising profile'), __('You meet our guide score. An adviser can confirm your options and the documents you will need.')],
                [0, __('Needs a closer look'), __('Your score is below our guide, but there may still be a pathway. Talk to an adviser about ways to strengthen your profile.')],
            ] as $i => [$min, $title, $text]) {
                $assessment->bands()->create(['min_percent' => $min, 'title' => $title, 'text' => $text, 'sort_order' => $i]);
            }

            return $assessment;
        });

        activity('skill_assessments')->causedBy($request->user())->performedOn($assessment)->log('Assessment created');
        $this->toast(['type' => 'success', 'message' => __('Assessment created. Add questions, then publish it.')]);

        return redirect()->route('admin.skill-assessments.edit', $assessment);
    }

    public function edit(SkillAssessment $assessment): Response
    {
        Gate::authorize('assessments.manage');

        $assessment->load(['country:id,name,iso2,is_active', 'bands', 'questions:id']);

        return Inertia::render('admin/skill/AssessmentEdit', [
            'assessment' => [
                'id' => $assessment->id,
                'country' => $assessment->country->name,
                'iso2' => strtolower($assessment->country->iso2),
                'country_active' => $assessment->country->is_active,
                'slug' => $assessment->slug,
                'title' => $assessment->title,
                'summary' => (string) $assessment->summary,
                'intro' => (string) $assessment->intro,
                'pass_percentage' => $assessment->pass_percentage,
                'minutes' => $assessment->minutes,
                'status' => $assessment->status,
                'bands' => $assessment->bands->map(fn (SkillAssessmentBand $b) => ['min_percent' => $b->min_percent, 'title' => $b->title, 'text' => $b->text])->values(),
                'questions' => $assessment->questions->map(fn (SkillAssessmentQuestion $q) => ['id' => $q->id, 'weight' => (int) $q->getRelationValue('pivot')->weight])->values(),
                'url' => route('skill-migration.show', $assessment->slug, false),
            ],
            'bank' => SkillAssessmentQuestion::query()->with(['category:id,name', 'options'])->orderBy('id')->get()
                ->map(fn (SkillAssessmentQuestion $q) => [
                    'id' => $q->id,
                    'text' => $q->text,
                    'category' => $q->category->name ?? __('General'),
                    'category_id' => $q->skill_assessment_category_id,
                    'is_active' => $q->is_active,
                    'options' => $q->options->count(),
                    'max_points' => (int) $q->options->max('points'),
                ])->values(),
            'categories' => SkillAssessmentCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, SkillAssessment $assessment): RedirectResponse
    {
        Gate::authorize('assessments.manage');

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(SkillAssessment::class)->ignore($assessment)],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:300'],
            'intro' => ['nullable', 'string', 'max:20000'],
            'pass_percentage' => ['required', 'integer', 'between:1,100'],
            'minutes' => ['nullable', 'integer', 'between:1,120'],
            'status' => ['required', Rule::in(array_keys(SkillAssessment::STATUSES))],
            'bands' => ['array', 'max:8'],
            'bands.*.min_percent' => ['required', 'integer', 'between:0,100'],
            'bands.*.title' => ['required', 'string', 'max:120'],
            'bands.*.text' => ['required', 'string', 'max:1000'],
            'questions' => ['array', 'max:100'],
            'questions.*.id' => ['required', 'integer', 'distinct', Rule::exists(SkillAssessmentQuestion::class, 'id')],
            'questions.*.weight' => ['required', 'integer', 'between:1,5'],
        ], ['slug.regex' => __('Use lowercase letters, numbers and dashes, e.g. australia or uk.')]);

        if ($data['status'] === 'published') {
            $live = SkillAssessmentQuestion::query()->whereKey(array_column($data['questions'] ?? [], 'id'))->where('is_active', true)->has('options', '>=', 2)->count();

            if ($live === 0) {
                throw ValidationException::withMessages(['status' => __('Add at least one active question (with two or more answers) before publishing.')]);
            }
        }

        DB::transaction(function () use ($assessment, $data, $request) {
            $assessment->update([
                ...Arr::only($data, ['slug', 'title', 'summary', 'pass_percentage', 'minutes', 'status']),
                'intro' => RichText::clean($data['intro'] ?? null) ?: null,
                'published_at' => $data['status'] === 'published' ? ($assessment->published_at ?? now()) : null,
                'updated_by' => $request->user()->id,
            ]);

            $assessment->bands()->delete();
            foreach (array_values($data['bands'] ?? []) as $i => $band) {
                $assessment->bands()->create([...$band, 'sort_order' => $i]);
            }

            $links = [];
            foreach (array_values($data['questions'] ?? []) as $i => $q) {
                $links[(int) $q['id']] = ['weight' => (int) $q['weight'], 'sort_order' => $i];
            }
            $assessment->questions()->sync($links);
        });

        activity('skill_assessments')->causedBy($request->user())->performedOn($assessment)->withProperties(['status' => $assessment->status])->log('Assessment updated');
        $this->toast(['type' => 'success', 'message' => __('Assessment saved.')]);

        return back();
    }

    public function destroy(Request $request, SkillAssessment $assessment): RedirectResponse
    {
        Gate::authorize('assessments.manage');

        $name = $assessment->country->name;
        $assessment->delete();
        activity('skill_assessments')->causedBy($request->user())->log("Assessment for {$name} deleted");
        $this->toast(['type' => 'success', 'message' => __('Assessment deleted. Its past submissions are kept.')]);

        return redirect()->route('admin.skill-assessments.index');
    }

    /** @return array{id: int, label: string, points: int} */
    public static function option(SkillAssessmentOption $o): array
    {
        return ['id' => $o->id, 'label' => $o->label, 'points' => $o->points];
    }
}
