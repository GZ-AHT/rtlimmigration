<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Course levels for universities and the homepage pathway finder (Figma: Diploma, Bachelor's, Master's). */
enum StudyLevel: string
{
    use HasOptions;

    case Foundation = 'foundation';
    case Diploma = 'diploma';
    case Bachelor = 'bachelor';
    case Master = 'master';
    case Phd = 'phd';
    case Language = 'language';

    public function label(): string
    {
        return match ($this) {
            self::Foundation => 'Foundation',
            self::Diploma => 'Diploma',
            self::Bachelor => "Bachelor's",
            self::Master => "Master's",
            self::Phd => 'PhD',
            self::Language => 'Language course',
        };
    }
}
