<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * REQ §12.5 rule 8 and §27 rule 10: a refund is requested, approved by an
 * authorised person, then processed by Accounts (or rejected).
 */
enum RefundStatus: string
{
    use HasOptions;

    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Processed = 'processed';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Awaiting approval',
            self::Approved => 'Approved, awaiting payout',
            self::Rejected => 'Rejected',
            self::Processed => 'Refunded',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Approved => 'info',
            self::Rejected => 'danger',
            self::Processed => 'success',
        };
    }

    /** Still holds money back from further refund requests. */
    public function isOpen(): bool
    {
        return $this === self::Requested || $this === self::Approved;
    }
}
