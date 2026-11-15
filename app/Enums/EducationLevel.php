<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** REQ §10 educational information (Bangladesh and international levels). */
enum EducationLevel: string
{
    use HasOptions;

    case Ssc = 'ssc';
    case Hsc = 'hsc';
    case Diploma = 'diploma';
    case Bachelor = 'bachelor';
    case Master = 'master';
    case Phd = 'phd';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ssc => 'SSC / O Level',
            self::Hsc => 'HSC / A Level',
            self::Diploma => 'Diploma',
            self::Bachelor => 'Bachelor\'s',
            self::Master => 'Master\'s',
            self::Phd => 'PhD',
            self::Other => 'Other',
        };
    }
}
