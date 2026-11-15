<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** REQ §7 suggested lead statuses. */
enum LeadStatus: string
{
    use HasOptions;

    case New = 'new';
    case Assigned = 'assigned';
    case Contacted = 'contacted';
    case FollowUp = 'follow_up';
    case Interested = 'interested';
    case Converted = 'converted';
    case NotInterested = 'not_interested';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Assigned => 'Assigned',
            self::Contacted => 'Contacted',
            self::FollowUp => 'Follow-up',
            self::Interested => 'Interested',
            self::Converted => 'Converted',
            self::NotInterested => 'Not interested',
            self::Rejected => 'Rejected',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Assigned, self::Contacted, self::FollowUp => 'warning',
            self::Interested, self::Converted => 'success',
            self::NotInterested => 'neutral',
            self::Rejected => 'danger',
        };
    }

    /** Final outcomes: the lead no longer needs working. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Converted, self::NotInterested, self::Rejected], true);
    }

    /**
     * Statuses a person may set by hand. Converted is only reached through
     * "Convert to student"; Assigned only through assignment.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::New, self::Contacted, self::FollowUp, self::Interested, self::NotInterested, self::Rejected];
    }
}
