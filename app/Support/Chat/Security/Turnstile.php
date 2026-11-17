<?php

namespace App\Support\Chat\Security;

use App\Support\Chat\ChatSecuritySettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile verification, done on the server with the secret
 * key. A failed or unreachable check counts as "not verified".
 */
class Turnstile
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private ChatSecuritySettings $settings) {}

    public function verify(?string $token, ?string $ip): bool
    {
        $secret = $this->settings->turnstileSecret();

        if ($secret === '' || blank($token) || strlen((string) $token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(8)->post(self::VERIFY_URL, array_filter([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (\Throwable $e) {
            Log::warning('Turnstile verification failed to connect.', ['error' => $e->getMessage()]);

            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
