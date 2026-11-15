<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How a student account came to exist (REQ §11.2). */
enum StudentSource: string
{
    use HasOptions;

    case SelfRegistration = 'self_registration';
    case Counselor = 'counselor';
    case LeadConversion = 'lead_conversion';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::SelfRegistration => 'Self-registered',
            self::Counselor => 'Created by counselor',
            self::LeadConversion => 'Converted from lead',
            self::Admin => 'Created by admin',
        };
    }
}
