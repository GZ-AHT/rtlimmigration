<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Where an application stands overall. The stage says which step it is
 * on; this says how it ended (or that it has not yet).
 */
enum ApplicationStatus: string
{
    use HasOptions;

    case InProgress = 'in_progress';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In progress',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Completed => 'Completed',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::InProgress => 'info',
            self::Approved, self::Completed => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'neutral',
        };
    }

    /** Still being worked on: stages can change. */
    public function isActive(): bool
    {
        return in_array($this, [self::InProgress, self::Approved, self::Rejected], true);
    }
}
