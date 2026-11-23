<?php

namespace App\Http\Controllers\Admin\SkillAssessments;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\SkillAssessmentSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Visitors' assessments, filterable by destination country and result. */
class SubmissionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('assessments.view');

        $filters = $request->validate([
            'country' => ['nullable', 'integer'],
            'result' => ['nullable', 'in:passed,below,started,preview'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $base = SkillAssessmentSubmission::query()
            ->when($filters['country'] ?? null, fn (Builder $q, int $c) => $q->where('country_id', $c));

        $submissions = (clone $base)
            ->when($filters['result'] ?? null, fn (Builder $q, string $r) => match ($r) {
                'passed' => $q->where('status', 'completed')->where('passed', true)->where('is_preview', false),
                'below' => $q->where('status', 'completed')->where('passed', false)->where('is_preview', false),
                'started' => $q->where('status', 'started'),
                default => $q->where('is_preview', true),
            }, fn (Builder $q) => $q->where('is_preview', false))
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $w) => $w->where('name', 'like', '%'.addcslashes($s, '%_\\').'%')
                ->orWhere('email', 'like', '%'.addcslashes($s, '%_\\').'%')->orWhere('phone', 'like', '%'.addcslashes($s, '%_\\').'%')))
            ->latest('id')->paginate(20)->withQueryString()
            ->through(fn (SkillAssessmentSubmission $s) => self::row($s));

        return Inertia::render('admin/skill/Submissions', [
            'submissions' => $submissions,
            'filters' => ['country' => (string) ($filters['country'] ?? ''), 'result' => $filters['result'] ?? '', 'search' => $filters['search'] ?? ''],
            'countries' => Country::query()->whereIn('id', SkillAssessmentSubmission::query()->select('country_id')->distinct())->orderBy('name')->get(['id', 'name'])
                ->map(fn (Country $c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            'counts' => [
                'passed' => (clone $base)->where('status', 'completed')->where('passed', true)->where('is_preview', false)->count(),
                'below' => (clone $base)->where('status', 'completed')->where('passed', false)->where('is_preview', false)->count(),
                'started' => (clone $base)->where('status', 'started')->count(),
            ],
        ]);
    }

    public function show(SkillAssessmentSubmission $submission): Response
    {
        Gate::authorize('assessments.view');

        $answers = $submission->answers ?? [];

        return Inertia::render('admin/skill/SubmissionShow', [
            'submission' => [
                ...self::row($submission),
                'phone' => $submission->phone,
                'pass_percentage' => $submission->snapshot['pass_percentage'],
                'categories' => $submission->category_scores ?? [],
                'band_title' => $submission->band_title,
                'band_text' => $submission->band_text,
                'lead_url' => $submission->lead_id ? route('admin.leads.show', $submission->lead_id, false) : null,
                'result_url' => $submission->isCompleted() ? route('skill-migration.result', $submission->token, false) : null,
                'answers' => array_map(function (array $q) use ($answers) {
                    $chosen = collect($q['options'])->firstWhere('id', $answers[$q['id']] ?? null);

                    return [
                        'question' => $q['text'],
                        'category' => $q['category'],
                        'answer' => $chosen['label'] ?? null,
                        'points' => isset($chosen) ? $chosen['points'] * $q['weight'] : null,
                        'max' => (max(array_column($q['options'], 'points') ?: [0])) * $q['weight'],
                    ];
                }, $submission->snapshot['questions']),
            ],
            'canDelete' => request()->user()->can('assessments.manage'),
        ]);
    }

    /** @return array<string, mixed> */
    public function destroy(Request $request, SkillAssessmentSubmission $submission): RedirectResponse
    {
        Gate::authorize('assessments.manage');

        $submission->delete();

        activity('skill-assessments')->causedBy($request->user())->log('Skill assessment submission deleted: '.$submission->name);
        $this->toast(['type' => 'success', 'message' => __('Submission deleted.')]);

        return to_route('admin.skill-assessments.submissions.index');
    }

    private static function row(SkillAssessmentSubmission $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'email' => $s->email,
            'country' => $s->country_name,
            'title' => $s->assessment_title,
            'status' => $s->status,
            'preview' => $s->is_preview,
            'score' => $s->score_percent !== null ? (float) $s->score_percent : null,
            'passed' => $s->passed,
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }
}
