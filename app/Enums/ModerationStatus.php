<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Moderation state of a chat message.
 *
 * Approved and Flagged messages are delivered (Flagged ones are queued for
 * a moderator to look at). Pending, Blocked and Rejected messages are held:
 * the sender still sees them with their status, staff who are not
 * moderators see only that a message is waiting, and nothing is deleted.
 */
enum ModerationStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Flagged = 'flagged';
    case Blocked = 'blocked';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Approved => 'Approved',
            self::Flagged => 'Flagged',
            self::Blocked => 'Blocked',
            self::Rejected => 'Rejected',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Flagged => 'info',
            self::Blocked, self::Rejected => 'danger',
        };
    }

    /** Not shown to the other side until a moderator approves it. */
    public function isHeld(): bool
    {
        return in_array($this, [self::Pending, self::Blocked, self::Rejected], true);
    }

    /** Waiting in the moderation queue. */
    public function needsReview(): bool
    {
        return in_array($this, [self::Pending, self::Flagged, self::Blocked], true);
    }

    /** @return list<string> */
    public static function heldValues(): array
    {
        return [self::Pending->value, self::Blocked->value, self::Rejected->value];
    }

    /** @return list<string> */
    public static function queueValues(): array
    {
        return [self::Pending->value, self::Flagged->value, self::Blocked->value];
    }
}
