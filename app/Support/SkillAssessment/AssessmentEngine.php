<?php

namespace App\Support\SkillAssessment;

use App\Models\SkillAssessment;
use App\Models\SkillAssessmentBand;
use App\Models\SkillAssessmentOption;
use App\Models\SkillAssessmentQuestion;
use Illuminate\Validation\ValidationException;

/**
 * Freezes a country's assessment into a snapshot when a visitor starts, and
 * scores their answers against that snapshot. Points never reach the
 * browser (publicQuestions strips them), and scoring happens only here.
 *
 * Score: each question is worth (its best option's points × its weight for
 * this country); the result is the share of those points the visitor's
 * answers earned, overall and per category.
 *
 * @phpstan-type Option array{id: int, label: string, points: int}
 * @phpstan-type Question array{id: int, text: string, help: string|null, category: string, weight: int, options: list<Option>}
 * @phpstan-type Band array{min_percent: int, title: string, text: string}
 * @phpstan-type Snapshot array{pass_percentage: int, questions: list<Question>, bands: list<Band>}
 */
class AssessmentEngine
{
    /** @return Snapshot */
    public function snapshot(SkillAssessment $assessment): array
    {
        $assessment->loadMissing(['liveQuestions.options', 'liveQuestions.category', 'bands']);

        return [
            'pass_percentage' => $assessment->pass_percentage,
            'questions' => array_values($assessment->liveQuestions->map(fn (SkillAssessmentQuestion $q) => [
                'id' => $q->id,
                'text' => $q->text,
                'help' => $q->help,
                'category' => $q->category !== null ? $q->category->name : 'General',
                'weight' => max(1, (int) $q->getRelationValue('pivot')->weight),
                'options' => array_values($q->options->map(fn (SkillAssessmentOption $o) => ['id' => $o->id, 'label' => $o->label, 'points' => $o->points])->all()),
            ])->all()),
            'bands' => array_values($assessment->bands->map(fn (SkillAssessmentBand $b) => ['min_percent' => $b->min_percent, 'title' => $b->title, 'text' => $b->text])->all()),
        ];
    }

    /**
     * What the visitor's browser gets: questions and option labels, never points.
     *
     * @param  Snapshot  $snapshot
     * @return list<array{id: int, text: string, help: string|null, category: string, options: list<array{id: int, label: string}>}>
     */
    public function publicQuestions(array $snapshot): array
    {
        return array_map(fn (array $q) => [
            'id' => $q['id'],
            'text' => $q['text'],
            'help' => $q['help'],
            'category' => $q['category'],
            'options' => array_map(fn (array $o) => ['id' => $o['id'], 'label' => $o['label']], $q['options']),
        ], $snapshot['questions']);
    }

    /**
     * Checks that every question has exactly one answer from its own options.
     *
     * @param  Snapshot  $snapshot
     * @param  array<mixed>  $answers  question id => option id
     * @return array<int, int>
     */
    public function validate(array $snapshot, array $answers): array
    {
        $clean = [];
        $missing = 0;

        foreach ($snapshot['questions'] as $q) {
            $chosen = $answers[$q['id']] ?? null;
            $ids = array_column($q['options'], 'id');

            if (! is_numeric($chosen) || ! in_array((int) $chosen, $ids, true)) {
                $missing++;

                continue;
            }

            $clean[$q['id']] = (int) $chosen;
        }

        if ($missing > 0) {
            throw ValidationException::withMessages(['answers' => trans_choice('Please answer the remaining question.|Please answer the :count remaining questions.', $missing, ['count' => $missing])]);
        }

        return $clean;
    }

    /**
     * @param  Snapshot  $snapshot
     * @param  array<int, int>  $answers  validated
     * @return array{percent: float, passed: bool, categories: list<array{category: string, percent: float}>, band: Band|null}
     */
    public function score(array $snapshot, array $answers): array
    {
        $earned = 0;
        $possible = 0;
        $byCategory = [];

        foreach ($snapshot['questions'] as $q) {
            $points = array_column($q['options'], 'points', 'id');
            $max = (max($points ?: [0])) * $q['weight'];
            $got = ($points[$answers[$q['id']] ?? 0] ?? 0) * $q['weight'];

            $earned += $got;
            $possible += $max;
            $byCategory[$q['category']] ??= ['earned' => 0, 'possible' => 0];
            $byCategory[$q['category']]['earned'] += $got;
            $byCategory[$q['category']]['possible'] += $max;
        }

        $percent = $possible > 0 ? round($earned / $possible * 100, 2) : 0.0;
        $bands = $snapshot['bands'];
        usort($bands, fn ($a, $b) => $b['min_percent'] <=> $a['min_percent']);
        $band = collect($bands)->first(fn (array $b) => $percent >= $b['min_percent']);

        return [
            'percent' => $percent,
            'passed' => $percent >= $snapshot['pass_percentage'],
            'categories' => array_map(
                fn (string $name, array $c) => ['category' => $name, 'percent' => $c['possible'] > 0 ? round($c['earned'] / $c['possible'] * 100, 1) : 0.0],
                array_keys($byCategory),
                $byCategory,
            ),
            'band' => $band,
        ];
    }
}
