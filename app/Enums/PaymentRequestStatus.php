<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * REQ §12.4 payment statuses, at the level of a payment request (bill).
 * Derived from its payments by PaymentLedger::recalculate(), never set by
 * hand, except Cancelled (an unpaid request withdrawn with a reason).
 */
enum PaymentRequestStatus: string
{
    use HasOptions;

    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Due = 'due';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case RefundRequested = 'refund_requested';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Pending => 'Pending verification',
            self::Partial => 'Partially paid',
            self::Paid => 'Paid',
            self::Due => 'Overdue',
            self::Failed => 'Failed',
            self::Rejected => 'Rejected',
            self::RefundRequested => 'Refund requested',
            self::Refunded => 'Refunded',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Paid, self::Refunded => 'success',
            self::Pending, self::Partial, self::RefundRequested => 'warning',
            self::Due, self::Failed, self::Rejected => 'danger',
            self::Unpaid => 'info',
            self::Cancelled => 'neutral',
        };
    }

    /** Still expects money from the student. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Unpaid, self::Pending, self::Partial, self::Due, self::Failed, self::Rejected], true);
    }
}
