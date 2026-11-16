<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** One online payment attempt (REQ §12.3, §12.5 rules 4, 5 and 9). */
enum GatewayTransactionStatus: string
{
    use HasOptions;

    case Initiated = 'initiated';
    case Paid = 'paid';
    case Review = 'review';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Started, not completed',
            self::Paid => 'Paid',
            self::Review => 'Held for review',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled by payer',
            self::Invalid => 'Failed validation',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Initiated => 'neutral',
            self::Paid => 'success',
            self::Review => 'warning',
            self::Failed, self::Invalid => 'danger',
            self::Cancelled => 'info',
        };
    }

    /** The money question is answered; later callbacks change nothing. */
    public function isSettled(): bool
    {
        return $this === self::Paid || $this === self::Review;
    }
}
