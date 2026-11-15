<?php

namespace App\Enums;

/** REQ §6 "Active / Inactive Users". */
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
