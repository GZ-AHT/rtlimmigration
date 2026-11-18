<?php

namespace App\Http\Controllers\Website;

use App\Actions\Leads\CaptureLead;
use App\Enums\LeadSource;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\SkillAssessment;
use App\Models\SkillAssessmentQuestion;
use App\Models\SkillAssessmentSubmission;
use App\Models\User;
use App\Notifications\NewLeadReceived;
use App\Support\Cms\WebsiteSeo;
use App\Support\SkillAssessment\AssessmentEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Country-based skill migration self-assessment for website visitors:
 * choose a destination, read its introduction, give contact details,
 * answer that country's questions, see the result. Each finished
 * assessment becomes a lead. Only the visitor who started an assessment
 * (by session) and staff can open it.
 */
class SkillMigrationController extends Controller
{
    private const SESSION_KEY = 'skill_assessments';

    public function __construct(private AssessmentEngine $engine) {}

    public function index(Request $request): Response
    {
        $search = Str::limit(trim((string) $request->query('q', '')), 60, '');

        return Inertia::render('website/skill/Index', [
            'countries' => self::published($search),
            'search' => $search,
            'seo' => WebsiteSeo::make(__('Skill migration assessment'), __('Check how your skills, experience and English compare with what each destination looks for.')),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $assessment = $this->findForVisitor($request, $slug);
        $questions = $assessment->liveQuestions()->with('category:id,name')->get();

        return Inertia::render('website/skill/Intro', [
            'assessment' => [
                ...self::card($assessment),
                'intro' => (string) $assessment->intro,
                'pass_percentage' => $assessment->pass_percentage,
                'minutes' => $assessment->minutes,
                'questions' => $questions->count(),
                'categories' => $questions->map(fn (SkillAssessmentQuestion $q) => $q->category !== null ? $q->category->name : 'General')->unique()->values(),
                'preview' => ! $assessment->isPublished(),
            ],
            'countries' => self::published(),
            'seo' => WebsiteSeo::make($assessment->title, $assessment->summary, noindex: ! $assessment->isPublished()),
        ]);
    }

    public function start(Request $request, string $slug): RedirectResponse
    {
        $assessment = $this->findForVisitor($request, $slug);

        // Honeypot: real visitors never see or fill this field.
        if ($request->filled('website')) {
            return back();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+()\-\s]{6,32}$/'],
            'consent' => ['accepted'],
        ], [
            'phone.regex' => __('Enter a valid phone number, including the country code.'),
            'consent.accepted' => __('Please agree so we can share your result and follow up.'),
        ]);

        $snapshot = $this->engine->snapshot($assessment);

        if ($snapshot['questions'] === []) {
            return back()->withErrors(['assessment' => __('This assessment isn’t ready yet. Please choose another country or contact us.')]);
        }

        $submission = SkillAssessmentSubmission::create([
            ...$data,
            'token' => Str::random(48),
            'skill_assessment_id' => $assessment->id,
            'country_id' => $assessment->country_id,
            'country_name' => $assessment->country->name,
            'assessment_title' => $assessment->title,
            'status' => 'started',
            'is_preview' => ! $assessment->isPublished(),
            'snapshot' => $snapshot,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        $request->session()->push(self::SESSION_KEY, $submission->token);

        return redirect()->route('skill-migration.take', $submission->token);
    }

    public function take(Request $request, string $token): Response|RedirectResponse
    {
        $submission = $this->owned($request, $token);

        if ($submission->isCompleted()) {
            return redirect()->route('skill-migration.result', $token);
        }

        return Inertia::render('website/skill/Take', [
            'submission' => [
                'token' => $submission->token,
                'name' => $submission->name,
                'country' => $submission->country_name,
                'iso2' => strtolower((string) $submission->country?->iso2),
                'title' => $submission->assessment_title,
                'slug' => $submission->assessment?->slug,
                'preview' => $submission->is_preview,
            ],
            'questions' => $this->engine->publicQuestions($submission->snapshot),
            'seo' => WebsiteSeo::make($submission->assessment_title, null, noindex: true),
        ]);
    }

    public function submit(Request $request, string $token, CaptureLead $capture): RedirectResponse
    {
        $submission = $this->owned($request, $token);

        // A finished assessment can't be answered again or changed.
        if ($submission->isCompleted()) {
            return redirect()->route('skill-migration.result', $token);
        }

        $answers = $this->engine->validate($submission->snapshot, (array) $request->input('answers', []));
        $result = $this->engine->score($submission->snapshot, $answers);

        DB::transaction(function () use ($submission, $answers, $result, $capture, $request) {
            $submission->update([
                'status' => 'completed',
                'answers' => $answers,
                'score_percent' => $result['percent'],
                'passed' => $result['passed'],
                'category_scores' => $result['categories'],
                'band_title' => $result['band']['title'] ?? null,
                'band_text' => $result['band']['text'] ?? null,
                'completed_at' => now(),
            ]);

            if (! $submission->is_preview) {
                $lead = $capture([
                    'name' => $submission->name,
                    'email' => $submission->email,
                    'phone' => $submission->phone,
                    'country_id' => $submission->country_id,
                    'service_id' => Service::query()->where('slug', 'skilled-migration')->value('id'),
                    'message' => __('Skill migration assessment for :country: :score% (:outcome the :pass% guide).:band', [
                        'country' => $submission->country_name,
                        'score' => rtrim(rtrim(number_format($result['percent'], 2), '0'), '.'),
                        'outcome' => $result['passed'] ? __('meets') : __('below'),
                        'pass' => $submission->snapshot['pass_percentage'],
                        'band' => $result['band'] ? ' '.__('Recommendation: :title', ['title' => $result['band']['title']]) : '',
                    ]),
                    'consent' => true,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ], LeadSource::SkillAssessment);

                $submission->update(['lead_id' => $lead->id]);
                $lead->load('service');

                Notification::send(
                    User::permission('leads.assign')->where('status', UserStatus::Active->value)->get()
                        ->merge(User::role(Role::SuperAdmin->value)->where('status', UserStatus::Active->value)->get())
                        ->unique('id'),
                    new NewLeadReceived($lead),
                );
            }
        });

        return redirect()->route('skill-migration.result', $token);
    }

    public function result(Request $request, string $token): Response|RedirectResponse
    {
        $submission = $this->owned($request, $token, staffMayView: true);

        if (! $submission->isCompleted()) {
            return redirect()->route('skill-migration.take', $token);
        }

        return Inertia::render('website/skill/Result', [
            'result' => [
                'name' => $submission->name,
                'country' => $submission->country_name,
                'iso2' => strtolower((string) $submission->country?->iso2),
                'title' => $submission->assessment_title,
                'slug' => $submission->assessment?->slug,
                'score' => (float) $submission->score_percent,
                'pass_percentage' => $submission->snapshot['pass_percentage'],
                'passed' => (bool) $submission->passed,
                'categories' => $submission->category_scores ?? [],
                'band_title' => $submission->band_title,
                'band_text' => $submission->band_text,
                'preview' => $submission->is_preview,
            ],
            'countries' => self::published(),
            'seo' => WebsiteSeo::make(__('Your result'), null, noindex: true),
        ]);
    }

    /**
     * Published assessments for the search box and country switchers.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function published(string $search = ''): Collection
    {
        return SkillAssessment::query()->published()->has('liveQuestions')
            ->with('country:id,name,iso2,sort_order')
            ->when($search !== '', fn (Builder $q) => $q->whereHas('country', fn (Builder $c) => $c->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->get()
            ->sortBy(fn (SkillAssessment $a) => [$a->country->sort_order, $a->country->name])
            ->map(fn (SkillAssessment $a) => self::card($a))
            ->values();
    }

    /** @return array<string, mixed> */
    private static function card(SkillAssessment $a): array
    {
        return [
            'slug' => $a->slug,
            'url' => route('skill-migration.show', $a->slug, false),
            'country' => $a->country->name,
            'iso2' => strtolower($a->country->iso2),
            'title' => $a->title,
            'summary' => (string) $a->summary,
        ];
    }

    /** A published assessment, or an unpublished one for staff previewing it. */
    private function findForVisitor(Request $request, string $slug): SkillAssessment
    {
        $assessment = SkillAssessment::query()->where('slug', $slug)->with('country')->firstOrFail();

        abort_if(! $assessment->isPublished() && ! $request->user()?->can('assessments.manage'), 404);

        return $assessment;
    }

    private function owned(Request $request, string $token, bool $staffMayView = false): SkillAssessmentSubmission
    {
        $submission = SkillAssessmentSubmission::query()->where('token', $token)->with(['country:id,iso2', 'assessment:id,slug'])->firstOrFail();
        $mine = in_array($token, (array) $request->session()->get(self::SESSION_KEY, []), true);

        abort_unless($mine || ($staffMayView && $request->user()?->can('assessments.view')), 404);

        return $submission;
    }
}
