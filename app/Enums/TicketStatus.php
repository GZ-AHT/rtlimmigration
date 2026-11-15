<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** REQ §7 "Open / Pending / Resolved / Closed Tickets". */
enum TicketStatus: string
{
    use HasOptions;

    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::Pending => 'warning',
            self::Resolved => 'success',
            self::Closed => 'neutral',
        };
    }
}
