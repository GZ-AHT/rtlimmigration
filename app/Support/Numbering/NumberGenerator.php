<?php

namespace App\Support\Numbering;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Issues unique, gap-tolerant sequential numbers (e.g. INV-202611-00042).
 *
 * The counter row is locked with SELECT ... FOR UPDATE inside a transaction,
 * so concurrent requests can never receive the same number. If the caller's
 * surrounding transaction rolls back, the number is released with it.
 */
class NumberGenerator
{
    public function next(string $type, ?CarbonInterface $at = null): string
    {
        $config = config("numbering.types.{$type}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Unknown number type [{$type}].");
        }

        $at ??= Date::now();
        $period = match ($config['reset'] ?? 'never') {
            'yearly' => $at->format('Y'),
            'monthly' => $at->format('Ym'),
            default => '',
        };

        $value = DB::transaction(function () use ($type, $period) {
            DB::table('number_sequences')->insertOrIgnore([
                'type' => $type,
                'period' => $period,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('number_sequences')
                ->where('type', $type)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            $next = $row->last_value + 1;

            DB::table('number_sequences')
                ->where('id', $row->id)
                ->update(['last_value' => $next, 'updated_at' => now()]);

            return $next;
        });

        return strtr($config['format'], [
            '{prefix}' => $config['prefix'],
            '{Y}' => $at->format('Y'),
            '{y}' => $at->format('y'),
            '{m}' => $at->format('m'),
            '{seq}' => str_pad((string) $value, $config['pad'] ?? 0, '0', STR_PAD_LEFT),
        ]);
    }
}
