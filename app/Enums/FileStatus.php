<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Administrative state of a student file (REQ §11.2 "File status").
 * Visa progress has its own stage workflow in Phase 6 (REQ §13).
 */
enum FileStatus: string
{
    use HasOptions;

    case Open = 'open';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::OnHold => 'On hold',
            self::Completed => 'Completed',
            self::Closed => 'Closed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::OnHold => 'warning',
            self::Completed => 'success',
            self::Closed => 'neutral',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::OnHold], true);
    }
}
