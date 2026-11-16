<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What moving an application to a stage does (REQ §13, §13.1, §13.2).
 * Standard stages only record progress; the others carry a rule.
 */
enum StageKind: string
{
    use HasOptions;

    case Standard = 'standard';
    case Decision = 'decision';
    case VisitRequested = 'visit_requested';
    case VisitCompleted = 'visit_completed';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Progress step',
            self::Decision => 'Decision (approved or rejected)',
            self::VisitRequested => 'Office visit requested',
            self::VisitCompleted => 'Office visit completed',
            self::Final => 'Process completed',
        };
    }
}
