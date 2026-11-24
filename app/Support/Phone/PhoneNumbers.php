<?php

namespace App\Support\Phone;

use Giggsey\Locale\Locale;
use Illuminate\Support\Facades\Cache;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * International phone numbers, validated with Google's libphonenumber rules
 * for the number's own country (no single digit limit), and kept in E.164.
 */
class PhoneNumbers
{
    public const DEFAULT_COUNTRY = 'BD';

    /**
     * The number in E.164 (+8801712345678), or null when it is not a valid
     * number. `$country` is only the default for numbers typed without a
     * country code; "+880…", "880…" and "01…" all give the same result, and
     * a "+" number from another country is judged by its own country.
     * In Bangladesh only mobile numbers are accepted.
     */
    public static function normalize(string $input, string $country = self::DEFAULT_COUNTRY): ?string
    {
        $util = PhoneNumberUtil::getInstance();
        $country = strtoupper($country);

        try {
            $number = $util->parse(trim($input), $country);
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isValidNumber($number)) {
            return null;
        }

        if ($util->getRegionCodeForNumber($number) === 'BD' && ! in_array($util->getNumberType($number), [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE], true)) {
            return null;
        }

        return $util->format($number, PhoneNumberFormat::E164);
    }

    /** A sample national number for a country, for the error message ("01712 345678"). */
    public static function example(string $country): ?string
    {
        $util = PhoneNumberUtil::getInstance();
        $sample = $util->getExampleNumberForType(strtoupper($country), PhoneNumberType::MOBILE) ?? $util->getExampleNumber(strtoupper($country));

        return $sample ? $util->format($sample, PhoneNumberFormat::NATIONAL) : null;
    }

    /**
     * A country's English name. Uses the names bundled with libphonenumber, so it works on servers
     * without PHP's intl extension.
     */
    public static function countryName(string $code): string
    {
        $code = strtoupper($code);

        try {
            $name = Locale::getDisplayRegion("-{$code}", 'en');
        } catch (\Throwable) {
            return $code;
        }

        return $name !== '' ? $name : $code;
    }

    /** The country (ISO code) an E.164 number belongs to. */
    public static function regionOf(string $e164): ?string
    {
        $util = PhoneNumberUtil::getInstance();

        try {
            $region = $util->getRegionCodeForNumber($util->parse($e164));
        } catch (NumberParseException) {
            return null;
        }

        return $region !== null && $region !== '001' ? $region : null;
    }

    public static function isCountry(string $code): bool
    {
        return in_array(strtoupper($code), PhoneNumberUtil::getInstance()->getSupportedRegions(), true);
    }

    /**
     * Every country with its dialing code and flag, for the country picker.
     *
     * @return list<array{code: string, name: string, dial: string, flag: string}>
     */
    public static function countries(): array
    {
        return Cache::rememberForever('phone.countries.v1', function (): array {
            $util = PhoneNumberUtil::getInstance();
            $countries = [];

            foreach ($util->getSupportedRegions() as $code) {
                $name = self::countryName($code);

                $countries[] = [
                    'code' => $code,
                    'name' => $name,
                    'dial' => '+'.$util->getCountryCodeForRegion($code),
                    'flag' => mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65),
                ];
            }

            usort($countries, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

            return $countries;
        });
    }
}
