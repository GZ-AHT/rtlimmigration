<?php

namespace App\Support\Webhooks;

/** Keeps webhooks pointed at the public internet, never at this server's own network (SSRF). */
class WebhookUrl
{
    /** @return string|null why the address isn't allowed, or null when it is */
    public static function problem(string $url): ?string
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return __('Use a full https:// address.');
        }

        $host = $parts['host'];
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            return __('That address could not be found.');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return __('Addresses inside a private network can’t be used.');
            }
        }

        return null;
    }
}
