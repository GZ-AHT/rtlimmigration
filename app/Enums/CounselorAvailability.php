<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** REQ §8 "Counselor Status". */
enum CounselorAvailability: string
{
    use HasOptions;

    case Available = 'available';
    case Busy = 'busy';
    case OnLeave = 'on_leave';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Busy => 'Busy',
            self::OnLeave => 'On leave',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Busy => 'warning',
            self::OnLeave => 'neutral',
        };
    }
}
