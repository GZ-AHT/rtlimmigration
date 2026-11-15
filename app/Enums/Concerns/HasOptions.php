<?php

namespace App\Enums\Concerns;

/**
 * For string-backed enums with label(): a list of {value, label} pairs for
 * select boxes and filters.
 */
trait HasOptions
{
    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
