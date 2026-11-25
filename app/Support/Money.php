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

    /** 500050 → "Five Thousand Taka and Fifty Paisa Only" (South Asian lakh/crore grouping). */
    public static function words(int $minor, string $currency = 'BDT'): string
    {
        $major = intdiv($minor, 100);
        $minorPart = $minor % 100;
        $unit = $currency === 'BDT' ? ['Taka', 'Paisa'] : [$currency, 'Cents'];

        $text = self::spell($major).' '.$unit[0];

        if ($minorPart > 0) {
            $text .= ' and '.self::spell($minorPart).' '.$unit[1];
        }

        return $text.' Only';
    }

    private static function spell(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }

        $small = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $below100 = fn (int $x): string => $x < 20 ? $small[$x] : trim($tens[intdiv($x, 10)].' '.$small[$x % 10]);

        $parts = [];

        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand', 100 => 'Hundred'] as $size => $name) {
            if ($n >= $size) {
                $count = intdiv($n, $size);
                $parts[] = self::spell($count).' '.$name;
                $n %= $size;
            }
        }

        if ($n > 0) {
            $parts[] = $below100($n);
        }

        return implode(' ', $parts);
    }
}
