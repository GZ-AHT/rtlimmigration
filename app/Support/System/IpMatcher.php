<?php

namespace App\Support\System;

use Symfony\Component\HttpFoundation\IpUtils;

/** Matches a visitor's address against lists of IPs and ranges (REQ §23.2 IP restrictions). */
class IpMatcher
{
    public static function valid(string $entry): bool
    {
        if (str_contains($entry, '/')) {
            [$ip, $bits] = explode('/', $entry, 2);

            return filter_var($ip, FILTER_VALIDATE_IP) !== false && ctype_digit($bits)
                && (int) $bits <= (str_contains($ip, ':') ? 128 : 32);
        }

        return filter_var($entry, FILTER_VALIDATE_IP) !== false;
    }

    /** @param list<string> $list */
    public static function matches(?string $ip, array $list): bool
    {
        return $ip !== null && $list !== [] && IpUtils::checkIp($ip, $list);
    }
}
