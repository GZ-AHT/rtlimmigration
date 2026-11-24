<?php

namespace App\Enums;

enum ConsultationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::Completed => 'info',
            self::Cancelled => 'danger',
        };
    }

    /** A booking that still holds its time slot. */
    public function holdsSlot(): bool
    {
        return $this === self::Pending || $this === self::Confirmed;
    }

    /** @return list<string> */
    public static function holding(): array
    {
        return [self::Pending->value, self::Confirmed->value];
    }
}
