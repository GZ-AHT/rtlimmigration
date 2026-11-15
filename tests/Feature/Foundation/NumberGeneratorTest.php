<?php

use App\Support\Numbering\NumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->numbers = app(NumberGenerator::class));

it('issues sequential padded numbers in the configured format', function () {
    $at = CarbonImmutable::parse('2026-11-15');

    expect($this->numbers->next('student', $at))->toBe('STU-2026-00001')
        ->and($this->numbers->next('student', $at))->toBe('STU-2026-00002')
        ->and($this->numbers->next('invoice', $at))->toBe('INV-202611-00001');
});

it('restarts the counter when the period changes', function () {
    expect($this->numbers->next('invoice', CarbonImmutable::parse('2026-11-30')))->toBe('INV-202611-00001')
        ->and($this->numbers->next('invoice', CarbonImmutable::parse('2026-12-01')))->toBe('INV-202612-00001')
        ->and($this->numbers->next('student', CarbonImmutable::parse('2026-12-31')))->toBe('STU-2026-00001')
        ->and($this->numbers->next('student', CarbonImmutable::parse('2027-01-01')))->toBe('STU-2027-00001');
});

it('never resets counters configured not to', function () {
    expect($this->numbers->next('ticket', CarbonImmutable::parse('2026-01-01')))->toBe('TKT-000001')
        ->and($this->numbers->next('ticket', CarbonImmutable::parse('2027-06-01')))->toBe('TKT-000002');
});

it('keeps types independent', function () {
    $this->numbers->next('student');
    $this->numbers->next('student');

    expect($this->numbers->next('file'))->toEndWith('-00001');
});

it('rejects unknown types', function () {
    $this->numbers->next('nonsense');
})->throws(InvalidArgumentException::class);
