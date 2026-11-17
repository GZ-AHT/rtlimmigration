<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** REQ §19.2: a mailbox can be suspended without deleting its mail. */
enum EmailAccountStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function tone(): string
    {
        return $this === self::Active ? 'success' : 'warning';
    }
}
