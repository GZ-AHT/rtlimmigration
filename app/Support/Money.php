<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money is stored as integer minor units (poisha for BDT) so sums never
 * suffer floating-point error. Convert only at the edges: forms and display.
 */
class Money
{
    /** "5,000.50" or 5000.5 → 500050. Rejects more than 2 decimals or negatives. */
    public static function toMinor(string|int|float $amount): int
    {
        $value = str_replace([',', ' ', '৳'], '', trim((string) $amount));

        if (! preg_match('/^\d{1,13}(\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException("Invalid amount [{$amount}].");
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');

        return ((int) $whole) * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    /** 500050 → "5000.50" (for form inputs). */
    public static function toDecimal(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    /** 500050 → "৳5,000.50" (whole amounts drop ".00"). */
    public static function format(int $minor, string $currency = 'BDT'): string
    {
        $symbol = $currency === 'BDT' ? '৳' : $currency.' ';
        $decimals = $minor % 100 === 0 ? 0 : 2;

        return $symbol.number_format($minor / 100, $decimals);
    }

    /**
     * 500050 → "BDT 5,000.50". For PDFs and spreadsheets, where the ৳ sign
     * is missing from the embedded fonts.
     */
    public static function plain(int $minor, string $currency = 'BDT'): string
    {
        return $currency.' '.number_format($minor / 100, 2);
    }
}
