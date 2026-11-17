<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** REQ §3 "Email Delivery Logs": where each email this system sends stands. */
enum EmailMessageStatus: string
{
    use HasOptions;

    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Queued => 'info',
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }
}
