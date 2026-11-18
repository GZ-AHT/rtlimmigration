<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\SkillAssessment;
use App\Models\SkillAssessmentCategory;
use App\Models\SkillAssessmentQuestion;
use Illuminate\Database\Seeder;

/**
 * Starter skill migration assessments for five destinations, each with its
 * own questions, weights, passing percentage and recommendations.
 *
 * The questions and scores are an indicative self-check written for this
 * site, NOT any government's official points test. Have a licensed
 * migration adviser review them in Admin → Skill Migration before launch.
 * Safe to run again: existing categories, questions and assessments (by
 * name, text and slug) are left as admins edited them.
 */
class SkillAssessmentSeeder extends Seeder
{
    public function run(): void
    {
        Country::firstOrCreate(['iso2' => 'DE'], ['name' => 'Germany', 'is_destination' => true, 'is_active' => true, 'sort_order' => 75]);

        $categories = [];
        foreach (['Age', 'English & language', 'Education', 'Work experience', 'Occupation & recognition', 'Other factors'] as $i => $name) {
            $categories[$name] = SkillAssessmentCategory::firstOrCreate(['name' => $name], ['sort_order' => ($i + 1) * 10])->id;
        }

        // key => [category, question, help, [[answer, points], ...]]
        $bank = [
            'age' => ['Age', 'How old are you?', null, [['18–24', 8], ['25–32', 10], ['33–39', 7], ['40–44', 4], ['45 or older', 0]]],
            'english' => ['English & language', 'What is your English test result (IELTS or equivalent)?', 'Use your most recent overall score.', [['I haven’t taken a test yet', 0], ['Below 6.0', 2], ['6.0–6.5', 6], ['7.0–7.5', 8], ['8.0 or higher', 10]]],
            'german' => ['English & language', 'How well do you speak German?', null, [['Not at all', 0], ['Basic (A1–A2)', 5], ['Independent (B1–B2)', 8], ['Advanced (C1 or higher)', 10]]],
            'education' => ['Education', 'What is your highest completed qualification?', null, [['Secondary school (SSC / HSC)', 2], ['Diploma or trade qualification', 5], ['Bachelor’s degree', 8], ['Master’s degree', 9], ['Doctorate (PhD)', 10]]],
            'local_study' => ['Education', 'Have you studied in this destination country?', null, [['No', 0], ['Yes, less than two years', 5], ['Yes, two years or more', 10]]],
            'experience' => ['Work experience', 'How many years of skilled work experience do you have in your occupation (in the last 10 years)?', null, [['Less than 1 year', 0], ['1–2 years', 3], ['3–4 years', 6], ['5–7 years', 8], ['8 years or more', 10]]],
            'job_offer' => ['Work experience', 'Do you have a job offer from an employer in this country?', null, [['No', 0], ['I’m in discussion with an employer', 4], ['Yes, a written job offer', 10]]],
            'shortage' => ['Occupation & recognition', 'Is your occupation in a field that is often in demand (for example health, IT, engineering, construction trades or teaching)?', null, [['No', 0], ['I’m not sure', 3], ['Yes', 10]]],
            'recognition' => ['Occupation & recognition', 'Has your qualification or skill been assessed or recognised by the relevant authority?', null, [['Not started', 0], ['In progress', 5], ['Yes, positive result', 10]]],
            'partner' => ['Other factors', 'Which describes your partner, if you have one?', null, [['I don’t have a partner, or they won’t come with me', 6], ['My partner has skills and good English', 10], ['My partner doesn’t have skilled work or English yet', 3]]],
            'funds' => ['Other factors', 'Can you show savings to support yourself after you arrive?', null, [['Not yet', 0], ['Partly', 5], ['Yes, comfortably', 10]]],
            'family' => ['Other factors', 'Do you have close family who are citizens or residents of this country?', null, [['No', 4], ['Yes', 10]]],
        ];

        $questions = [];
        foreach ($bank as $key => [$category, $text, $help, $options]) {
            $question = SkillAssessmentQuestion::firstOrCreate(['text' => $text], ['skill_assessment_category_id' => $categories[$category], 'help' => $help, 'is_active' => true]);

            if ($question->wasRecentlyCreated) {
                foreach ($options as $i => [$label, $points]) {
                    $question->options()->create(['label' => $label, 'points' => $points, 'sort_order' => $i]);
                }
            }

            $questions[$key] = $question->id;
        }

        $disclaimer = '<p><strong>This free self-check gives an indication only.</strong> It is not an official points test, and the final decision always rests with the immigration authorities. An RTL adviser can review your full profile.</p>';

        // slug => [iso2, title, summary, intro, pass %, minutes, [question key => weight], bands]
        $assessments = [
            'australia' => ['AU', 'Australia skilled migration assessment', 'See how your age, English, qualifications and experience compare for skilled migration to Australia.', 'Australia welcomes skilled workers in occupations it needs. Answer a few questions about your age, English, qualifications and work experience to see how your profile compares.', 65, 5,
                ['age' => 1, 'english' => 2, 'education' => 1, 'local_study' => 1, 'experience' => 2, 'shortage' => 1, 'recognition' => 1, 'partner' => 1]],
            'canada' => ['CA', 'Canada skilled migration assessment', 'Check your profile for Canada’s skilled worker pathways: language, education, experience and more.', 'Canada’s skilled pathways look closely at language ability, education and work experience. This check helps you see where you stand and what could strengthen your profile.', 67, 5,
                ['age' => 1, 'english' => 2, 'education' => 2, 'local_study' => 1, 'experience' => 2, 'job_offer' => 1, 'partner' => 1, 'funds' => 1]],
            'uk' => ['GB', 'United Kingdom skilled worker assessment', 'See how ready you are for a UK skilled worker route, where a job offer matters most.', 'UK skilled work routes usually start with a job offer from an approved employer. Answer these questions to see how ready you are and what to focus on next.', 70, 4,
                ['english' => 2, 'education' => 1, 'experience' => 1, 'job_offer' => 3, 'shortage' => 1, 'funds' => 1]],
            'germany' => ['DE', 'Germany skilled immigration assessment', 'Check your profile for skilled work in Germany: recognised qualifications, experience and language.', 'Germany looks for skilled professionals with recognised qualifications. Language skills and a job offer can make a big difference. See how your profile compares.', 60, 5,
                ['age' => 1, 'german' => 2, 'english' => 1, 'education' => 1, 'experience' => 1, 'job_offer' => 2, 'recognition' => 2, 'funds' => 1]],
            'new-zealand' => ['NZ', 'New Zealand skilled migration assessment', 'See how your skills and experience compare for skilled migration to New Zealand.', 'New Zealand values skilled workers, especially with a job offer or in-demand skills. This check shows how your profile compares and where to strengthen it.', 65, 4,
                ['age' => 1, 'english' => 2, 'education' => 1, 'experience' => 1, 'job_offer' => 2, 'shortage' => 1, 'recognition' => 1, 'family' => 1]],
        ];

        foreach ($assessments as $slug => [$iso2, $title, $summary, $intro, $pass, $minutes, $weights]) {
            $country = Country::query()->where('iso2', $iso2)->first();

            if (! $country || SkillAssessment::query()->where('slug', $slug)->orWhere('country_id', $country->id)->exists()) {
                continue;
            }

            $assessment = SkillAssessment::create([
                'country_id' => $country->id, 'slug' => $slug, 'title' => $title, 'summary' => $summary,
                'intro' => "<p>{$intro}</p>{$disclaimer}", 'pass_percentage' => $pass, 'minutes' => $minutes,
                'status' => 'published', 'published_at' => now(),
            ]);

            $order = 0;
            foreach ($weights as $key => $weight) {
                if (isset($questions[$key])) {
                    $assessment->questions()->attach($questions[$key], ['weight' => $weight, 'sort_order' => $order++]);
                }
            }

            foreach ([
                [min(100, $pass + 15), 'Strong profile', "Your answers show a strong profile for skilled migration to {$country->name}. Book a consultation and we’ll help you plan the next steps and documents."],
                [$pass, 'Promising profile', "You meet our guide score for {$country->name}. An adviser can confirm the best pathway and what to prepare."],
                [0, 'Needs a closer look', "Your score is below our guide for {$country->name} for now. Improving your English score, getting your skills assessed or a job offer can make a big difference. Talk to an adviser about a plan."],
            ] as $i => [$min, $bandTitle, $text]) {
                $assessment->bands()->create(['min_percent' => $min, 'title' => $bandTitle, 'text' => $text, 'sort_order' => $i]);
            }
        }
    }
}
