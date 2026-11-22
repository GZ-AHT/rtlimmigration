<?php

use App\Enums\LeadSource;
use App\Enums\Role;
use App\Models\Country;
use App\Models\Lead;
use App\Models\Page;
use App\Models\SkillAssessment;
use App\Models\SkillAssessmentQuestion;
use App\Models\SkillAssessmentSubmission;
use App\Notifications\NewLeadReceived;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SkillAssessmentSeeder;
use Database\Seeders\WebsiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/* Country-based skill migration assessments: website journey and admin. */

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed([SettingsSeeder::class, ReferenceDataSeeder::class, WebsiteContentSeeder::class, SkillAssessmentSeeder::class]);
});

/** A small, predictable assessment: 2 questions, weights 1 and 2, pass mark 50. */
function miniAssessment(string $status = 'published'): SkillAssessment
{
    $country = Country::create(['name' => 'Testland', 'iso2' => 'TL', 'is_destination' => true, 'is_active' => true]);
    $assessment = SkillAssessment::create(['country_id' => $country->id, 'slug' => 'testland', 'title' => 'Testland check', 'pass_percentage' => 50, 'status' => $status]);

    $q1 = SkillAssessmentQuestion::create(['text' => 'Q one', 'is_active' => true]);
    $q1->options()->createMany([['label' => 'Low', 'points' => 0, 'sort_order' => 0], ['label' => 'High', 'points' => 10, 'sort_order' => 1]]);
    $q2 = SkillAssessmentQuestion::create(['text' => 'Q two', 'is_active' => true]);
    $q2->options()->createMany([['label' => 'Some', 'points' => 5, 'sort_order' => 0], ['label' => 'Lots', 'points' => 10, 'sort_order' => 1]]);
    $assessment->questions()->attach([$q1->id => ['weight' => 1, 'sort_order' => 0], $q2->id => ['weight' => 2, 'sort_order' => 1]]);
    $assessment->bands()->createMany([
        ['min_percent' => 80, 'title' => 'Great', 'text' => 'Great profile.', 'sort_order' => 0],
        ['min_percent' => 0, 'title' => 'Keep going', 'text' => 'Talk to us.', 'sort_order' => 1],
    ]);

    return $assessment;
}

/** Starts an assessment as a visitor and returns the submission. */
function startAs($test, string $slug): SkillAssessmentSubmission
{
    $test->post(route('skill-migration.start', $slug), ['name' => 'Rafi Ahmed', 'email' => 'rafi@example.com', 'phone' => '+880 1711 000000', 'consent' => true])
        ->assertRedirect();

    return SkillAssessmentSubmission::latest('id')->first();
}

function answersFor(SkillAssessmentSubmission $s, array $pick): array
{
    // $pick: question text => option label
    $answers = [];
    foreach ($s->snapshot['questions'] as $q) {
        $answers[$q['id']] = collect($q['options'])->firstWhere('label', $pick[$q['text']])['id'];
    }

    return $answers;
}

/* ------------------------------------------------------- country setup */

it('seeds a different assessment for each destination country', function () {
    $assessments = SkillAssessment::with(['country', 'questions'])->get()->keyBy('slug');

    expect($assessments->keys()->sort()->values()->all())->toBe(['australia', 'canada', 'germany', 'new-zealand', 'uk'])
        ->and($assessments['uk']->pass_percentage)->toBe(70)
        ->and($assessments['germany']->pass_percentage)->toBe(60)
        ->and($assessments['germany']->questions->pluck('text'))->toContain('How well do you speak German?')
        ->and($assessments['australia']->questions->pluck('text'))->not->toContain('How well do you speak German?')
        ->and($assessments['uk']->questions->count())->not->toBe($assessments['canada']->questions->count());

    // A question can belong to several countries.
    expect(SkillAssessmentQuestion::where('text', 'like', 'What is your English test result%')->first()->assessments()->count())->toBe(5);
});

it('lists only published assessments of active countries with questions in the homepage search', function () {
    miniAssessment('draft');
    Country::where('iso2', 'NZ')->update(['is_active' => false]);

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections', fn ($sections) => collect($sections)->firstWhere('type', 'skill_search') !== null)
        ->has('sections.4.records.countries', 4)
        ->where('sections.4.type', 'skill_search')
        ->where('sections.4.records.countries', fn ($c) => ! collect($c)->pluck('slug')->contains('testland') && ! collect($c)->pluck('slug')->contains('new-zealand')));

    $this->get('/skill-migration?q=can')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('website/skill/Index')
        ->has('countries', 1)
        ->where('countries.0.slug', 'canada'));
});

it('shows a country introduction, hides drafts from visitors and previews them for managers', function () {
    $this->get('/skill-migration/australia')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('website/skill/Intro')
        ->where('assessment.country', 'Australia')
        ->where('assessment.pass_percentage', 65)
        ->where('assessment.questions', 8)
        ->where('assessment.preview', false)
        ->has('countries', 5));

    miniAssessment('draft');
    $this->get('/skill-migration/testland')->assertNotFound();
    $this->get('/skill-migration/no-such-country')->assertNotFound();

    $this->actingAs($this->userWithRole(Role::SuperAdmin))->get('/skill-migration/testland')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('assessment.preview', true));
});

it('refuses to start an assessment that has no questions', function () {
    $assessment = miniAssessment();
    SkillAssessmentQuestion::query()->update(['is_active' => false]);

    $this->post(route('skill-migration.start', 'testland'), ['name' => 'A', 'email' => 'a@example.com', 'phone' => '+8801711000000', 'consent' => true])
        ->assertSessionHasErrors('assessment');

    expect(SkillAssessmentSubmission::count())->toBe(0)
        ->and($this->get('/skill-migration/testland')->inertiaProps('assessment.questions'))->toBe(0);
});

/* ------------------------------------------------------------- journey */

it('runs the whole journey: start, answer, score, lead, result', function () {
    Notification::fake();
    $manager = $this->userWithRole(Role::LeadManager);
    miniAssessment();

    $submission = startAs($this, 'testland');
    expect($submission->status)->toBe('started')->and($submission->country_name)->toBe('Testland');

    // The browser never receives the points.
    $this->get(route('skill-migration.take', $submission->token))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('website/skill/Take')
        ->where('submission.country', 'Testland')
        ->has('questions', 2)
        ->missing('questions.0.options.0.points'));

    // High (10/10, weight 1) + Some (5/10, weight 2) = 20 of 30 points = 66.67%.
    $this->post(route('skill-migration.submit', $submission->token), ['answers' => answersFor($submission, ['Q one' => 'High', 'Q two' => 'Some'])])
        ->assertRedirect(route('skill-migration.result', $submission->token));

    $submission->refresh();
    expect((float) $submission->score_percent)->toBe(66.67)
        ->and($submission->passed)->toBeTrue()
        ->and($submission->band_title)->toBe('Keep going')
        ->and($submission->lead)->not->toBeNull()
        ->and($submission->lead->source)->toBe(LeadSource::SkillAssessment)
        ->and($submission->lead->country_id)->toBe(Country::where('iso2', 'TL')->value('id'));
    Notification::assertSentTo($manager, NewLeadReceived::class);

    $this->get(route('skill-migration.result', $submission->token))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('website/skill/Result')
        ->where('result.score', 66.67)
        ->where('result.passed', true)
        ->where('result.pass_percentage', 50)
        ->has('result.categories', 1));
});

it('rejects missing answers and answers that belong to another question', function () {
    miniAssessment();
    $submission = startAs($this, 'testland');
    $answers = answersFor($submission, ['Q one' => 'High', 'Q two' => 'Lots']);
    [$first, $second] = array_keys($answers);

    $this->post(route('skill-migration.submit', $submission->token), ['answers' => [$first => $answers[$first]]])->assertSessionHasErrors('answers');
    $this->post(route('skill-migration.submit', $submission->token), ['answers' => [$first => $answers[$second], $second => $answers[$second]]])->assertSessionHasErrors('answers');

    expect($submission->fresh()->status)->toBe('started');
});

it('can’t be changed after submission, and keeps the rules from when it started', function () {
    miniAssessment();
    $submission = startAs($this, 'testland');

    // An admin changes points and the pass mark mid-way: this visitor keeps the original rules.
    SkillAssessmentQuestion::where('text', 'Q two')->first()->options()->update(['points' => 100]);
    SkillAssessment::where('slug', 'testland')->update(['pass_percentage' => 99]);

    $this->post(route('skill-migration.submit', $submission->token), ['answers' => answersFor($submission, ['Q one' => 'Low', 'Q two' => 'Lots'])]);
    expect((float) $submission->fresh()->score_percent)->toBe(66.67)->and($submission->fresh()->passed)->toBeTrue();

    // A second submission is ignored.
    $this->post(route('skill-migration.submit', $submission->token), ['answers' => answersFor($submission, ['Q one' => 'High', 'Q two' => 'Lots'])])
        ->assertRedirect(route('skill-migration.result', $submission->token));
    expect((float) $submission->fresh()->score_percent)->toBe(66.67)->and(Lead::count())->toBe(1);
});

it('only lets the visitor who started it (or staff) open an assessment', function () {
    miniAssessment();
    $submission = startAs($this, 'testland');
    $this->post(route('skill-migration.submit', $submission->token), ['answers' => answersFor($submission, ['Q one' => 'High', 'Q two' => 'Lots'])]);

    $this->flushSession();
    $this->get(route('skill-migration.result', $submission->token))->assertNotFound();
    $this->get(route('skill-migration.take', $submission->token))->assertNotFound();

    $this->actingAs($this->userWithRole(Role::LeadManager))->get(route('skill-migration.result', $submission->token))->assertOk();
});

it('does not create leads for staff preview runs', function () {
    miniAssessment('draft');
    $this->actingAs($this->userWithRole(Role::SuperAdmin));

    $submission = startAs($this, 'testland');
    $this->post(route('skill-migration.submit', $submission->token), ['answers' => answersFor($submission, ['Q one' => 'High', 'Q two' => 'Lots'])]);

    expect($submission->fresh()->is_preview)->toBeTrue()->and($submission->fresh()->status)->toBe('completed')->and(Lead::count())->toBe(0);
});

/* --------------------------------------------------------------- admin */

it('lets admins create, configure and publish a country assessment', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $country = Country::create(['name' => 'Ireland', 'iso2' => 'IE', 'is_destination' => true, 'is_active' => true]);

    $this->actingAs($admin)->post(route('admin.skill-assessments.store'), ['country_id' => $country->id, 'slug' => 'ireland', 'title' => 'Ireland check', 'pass_percentage' => 55])->assertRedirect();
    $assessment = SkillAssessment::where('slug', 'ireland')->first();
    expect($assessment->status)->toBe('draft')->and($assessment->bands()->count())->toBe(3);

    // One assessment per country.
    $this->actingAs($admin)->post(route('admin.skill-assessments.store'), ['country_id' => $country->id, 'slug' => 'ireland-2', 'title' => 'x', 'pass_percentage' => 50])->assertSessionHasErrors('country_id');

    $payload = ['slug' => 'ireland', 'title' => 'Ireland check', 'pass_percentage' => 55, 'status' => 'published', 'bands' => [['min_percent' => 0, 'title' => 'Hi', 'text' => 'Hello']], 'questions' => []];
    $this->actingAs($admin)->put(route('admin.skill-assessments.update', $assessment), $payload)->assertSessionHasErrors('status');

    $english = SkillAssessmentQuestion::where('text', 'like', 'What is your English test result%')->first();
    $age = SkillAssessmentQuestion::where('text', 'How old are you?')->first();
    $this->actingAs($admin)->put(route('admin.skill-assessments.update', $assessment), [...$payload, 'questions' => [['id' => $age->id, 'weight' => 1], ['id' => $english->id, 'weight' => 3]]])->assertSessionHasNoErrors();

    $assessment->refresh()->load('questions');
    expect($assessment->status)->toBe('published')
        ->and($assessment->questions->pluck('id')->all())->toBe([$age->id, $english->id])
        ->and($assessment->questions->last()->pivot->weight)->toBe(3);
    $this->get('/skill-migration/ireland')->assertOk();
});

it('manages questions with points and the countries that use them', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $uk = SkillAssessment::where('slug', 'uk')->first();
    $de = SkillAssessment::where('slug', 'germany')->first();

    $this->actingAs($admin)->post(route('admin.skill-assessments.questions.store'), [
        'text' => 'Do you hold a professional licence?', 'is_active' => true,
        'options' => [['label' => 'No', 'points' => 0], ['label' => 'Yes', 'points' => 10]],
        'assessment_ids' => [$uk->id, $de->id],
    ])->assertSessionHasNoErrors();

    $question = SkillAssessmentQuestion::where('text', 'Do you hold a professional licence?')->first();
    expect($question->options()->count())->toBe(2)->and($question->assessments()->pluck('slug')->sort()->values()->all())->toBe(['germany', 'uk']);

    $this->actingAs($admin)->post(route('admin.skill-assessments.questions.store'), ['text' => 'One answer', 'options' => [['label' => 'Only', 'points' => 1]]])->assertSessionHasErrors('options');
});

it('filters submissions by country and enforces permissions', function () {
    miniAssessment();
    $s = startAs($this, 'testland');
    $this->post(route('skill-migration.submit', $s->token), ['answers' => answersFor($s, ['Q one' => 'High', 'Q two' => 'Lots'])]);
    $this->flushSession();

    $testland = Country::where('iso2', 'TL')->value('id');
    $manager = $this->userWithRole(Role::LeadManager);

    $this->actingAs($manager)->get(route('admin.skill-assessments.submissions.index', ['country' => $testland]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('submissions.data', 1)->where('counts.passed', 1));
    $this->actingAs($manager)->get(route('admin.skill-assessments.submissions.index', ['country' => Country::where('iso2', 'AU')->value('id')]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('submissions.data', 0));
    $this->actingAs($manager)->get(route('admin.skill-assessments.submissions.show', $s))->assertOk();

    // Lead managers can view results but not change assessments.
    $this->actingAs($manager)->get(route('admin.skill-assessments.questions.index'))->assertForbidden();
    $this->actingAs($this->userWithRole(Role::Counselor))->get(route('admin.skill-assessments.index'))->assertForbidden();
});

it('lets assessment managers add and rename destination countries', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);

    $this->actingAs($admin)->post(route('admin.countries.store'), ['name' => 'Japan', 'iso2' => 'jp', 'is_destination' => true])->assertSessionHasNoErrors();
    $japan = Country::where('name', 'Japan')->first();
    expect($japan->iso2)->toBe('JP')->and($japan->is_active)->toBeTrue();

    $this->actingAs($admin)->patch(route('admin.countries.update', $japan), ['name' => 'Japan (Nippon)'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->patch(route('admin.countries.update', $japan), ['is_active' => false]);
    expect($japan->fresh()->name)->toBe('Japan (Nippon)')->and($japan->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->post(route('admin.countries.store'), ['name' => 'Japan again', 'iso2' => 'JP'])->assertSessionHasErrors('iso2');
});

it('adds the skill migration search to an existing homepage once', function () {
    $home = Page::where('system_key', 'home')->first();
    $home->update(['sections' => array_values(array_filter($home->sections, fn ($s) => $s['type'] !== 'skill_search'))]);

    $this->seed(WebsiteContentSeeder::class);
    $this->seed(WebsiteContentSeeder::class);

    expect(collect($home->fresh()->sections)->where('type', 'skill_search')->count())->toBe(1);
});
