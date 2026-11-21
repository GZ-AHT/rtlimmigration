<?php

namespace App\Support\System;

use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Google reCAPTCHA v3 verification for public forms (REQ §23.3). */
class Recaptcha
{
    public const MIN_SCORE = 0.5;

    public static function enabled(): bool
    {
        $settings = app(SettingsRepository::class);

        return (bool) $settings->get('integrations.recaptcha_enabled') && filled($settings->get('integrations.recaptcha_site_key')) && filled($settings->get('integrations.recaptcha_secret'));
    }

    /** A missing or low-scoring token fails. If Google can't be reached the form still works, so leads aren't lost. */
    public static function passes(?string $token, ?string $ip): bool
    {
        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => app(SettingsRepository::class)->get('integrations.recaptcha_secret'),
                'response' => $token,
                'remoteip' => $ip,
            ]);
        } catch (Throwable $e) {
            Log::warning('reCAPTCHA could not be verified: '.$e->getMessage());

            return true;
        }

        if (! $response->successful()) {
            Log::warning('reCAPTCHA verification returned HTTP '.$response->status());

            return true;
        }

        return $response->json('success') === true && (float) $response->json('score', 0) >= self::MIN_SCORE;
    }
}
