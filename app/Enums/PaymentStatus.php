<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * A single submitted payment (REQ §12.1 steps 4–7): submitted → (verified)
 * → confirmed or rejected. Verification and confirmation are separate
 * permissions so the two can be split between people if wanted.
 */
enum PaymentStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Verified = 'verified';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting verification',
            self::Verified => 'Verified, awaiting confirmation',
            self::Confirmed => 'Confirmed',
            self::Rejected => 'Rejected',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Verified => 'info',
            self::Confirmed => 'success',
            self::Rejected => 'danger',
        };
    }

    public function isAwaitingDecision(): bool
    {
        return $this === self::Pending || $this === self::Verified;
    }
}
