<?php

namespace App\Support\System;

use App\Support\Settings\SettingsRepository;

/**
 * System settings edited at System → Settings (REQ §23): maintenance, security
 * and integrations. One definition drives the admin form, validation and the
 * code that enforces each setting. Secrets are encrypted at rest and never
 * sent to the browser: the form only learns whether one is saved.
 */
class SystemSettings
{
    /** @return array<string, array{label: string, description: string, permission: string, fields: array<string, array<string, mixed>>}> */
    public static function groups(): array
    {
        return [
            'maintenance' => [
                'label' => 'Maintenance', 'permission' => 'settings.manage',
                'description' => 'Show visitors a maintenance page. People who can manage system settings can still sign in and use the admin panel.',
                'fields' => [
                    'enabled' => ['type' => 'toggle', 'label' => 'Maintenance mode is on'],
                    'message' => ['type' => 'textarea', 'label' => 'Message shown to visitors', 'max' => 300],
                    'starts_at' => ['type' => 'datetime', 'label' => 'Scheduled start (optional)', 'hint' => 'With an end time, maintenance switches on and off by itself.'],
                    'ends_at' => ['type' => 'datetime', 'label' => 'Scheduled end (optional)'],
                    'allow_ips' => ['type' => 'textarea', 'label' => 'Addresses that can still see the site', 'max' => 2000, 'hint' => 'One IP address per line, for example your office.'],
                ],
            ],
            'security' => [
                'label' => 'Security', 'permission' => 'security.manage',
                'description' => 'Password rules, sign-in limits, sessions and IP restrictions. Changes apply immediately; existing passwords are not changed.',
                'fields' => [
                    'password_min_length' => ['type' => 'number', 'label' => 'Minimum password length', 'min' => 8, 'max' => 64],
                    'password_mixed_case' => ['type' => 'toggle', 'label' => 'Require upper and lower case letters'],
                    'password_numbers' => ['type' => 'toggle', 'label' => 'Require a number'],
                    'password_symbols' => ['type' => 'toggle', 'label' => 'Require a symbol'],
                    'password_uncompromised' => ['type' => 'toggle', 'label' => 'Refuse passwords found in known data leaks', 'hint' => 'Checks the password against haveibeenpwned.com (only part of a hash is sent).'],
                    'login_max_attempts' => ['type' => 'number', 'label' => 'Sign-in attempts before a lockout', 'min' => 3, 'max' => 20],
                    'login_lockout_minutes' => ['type' => 'number', 'label' => 'Lockout length (minutes)', 'min' => 1, 'max' => 120],
                    'session_lifetime' => ['type' => 'number', 'label' => 'Sign out after this many idle minutes', 'min' => 15, 'max' => 1440],
                    'admin_ip_allowlist' => ['type' => 'textarea', 'label' => 'Staff area: allowed addresses', 'max' => 2000, 'hint' => 'One IP address or range (like 203.0.113.0/24) per line. Blank allows everyone. Staff outside the list are refused. Keep your own address here before saving.'],
                    'blocked_ips' => ['type' => 'textarea', 'label' => 'Blocked addresses (whole site)', 'max' => 2000, 'hint' => 'One IP address or range per line.'],
                    'notify_lockout' => ['type' => 'toggle', 'label' => 'Notify security managers when someone is locked out'],
                    'notify_new_ip' => ['type' => 'toggle', 'label' => 'Notify security managers when staff sign in from a new address'],
                ],
            ],
            'backups' => [
                'label' => 'Automatic backups', 'permission' => 'backups.manage',
                'description' => 'Backups are saved on this server in a private folder. Download them to keep a copy somewhere else too.',
                'fields' => [
                    'schedule' => ['type' => 'select', 'label' => 'Automatic backups', 'options' => ['off' => 'Off', 'daily' => 'Every night (2:00)', 'weekly' => 'Every Sunday night (2:00)'], 'hint' => 'Needs the scheduler (schedule:run) running on the server.'],
                    'include_files' => ['type' => 'toggle', 'label' => 'Also back up uploaded files and documents'],
                    'retention_days' => ['type' => 'number', 'label' => 'Delete automatic backups after this many days', 'min' => 1, 'max' => 365],
                ],
            ],
            'integrations' => [
                'label' => 'Integrations', 'permission' => 'integrations.manage',
                'description' => 'Third-party services. Secret keys are stored encrypted and are never shown again. Analytics, Tag Manager and search verification are under Site settings → SEO; payment gateways, email and live chat have their own screens.',
                'fields' => [
                    'fb_pixel_id' => ['type' => 'text', 'label' => 'Facebook Pixel ID', 'max' => 20, 'pattern' => '/^\d{8,20}$/', 'hint' => 'Digits only.'],
                    'recaptcha_enabled' => ['type' => 'toggle', 'label' => 'Protect website forms with Google reCAPTCHA v3'],
                    'recaptcha_site_key' => ['type' => 'text', 'label' => 'reCAPTCHA site key', 'max' => 100, 'pattern' => '/^[A-Za-z0-9_-]+$/'],
                    'recaptcha_secret' => ['type' => 'secret', 'label' => 'reCAPTCHA secret key', 'max' => 100],
                    'google_maps_key' => ['type' => 'text', 'label' => 'Google Maps key', 'max' => 80, 'pattern' => '/^[A-Za-z0-9_-]{20,80}$/', 'hint' => 'Shows a Google map on the contact page. Create a browser key with the Maps Embed API and restrict it to your website address in Google Cloud: the key is visible in the page, so the restriction is what protects it. Blank keeps the free OpenStreetMap map.'],
                    'whatsapp_enabled' => ['type' => 'toggle', 'label' => 'Show the WhatsApp button on the website', 'hint' => 'The number is set in Site settings → General.'],
                    'whatsapp_message' => ['type' => 'text', 'label' => 'WhatsApp opening message', 'max' => 200],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'maintenance.enabled' => false,
            'maintenance.message' => 'We’re doing some maintenance and will be back shortly.',
            'security.password_min_length' => 8, 'security.password_mixed_case' => false, 'security.password_numbers' => false,
            'security.password_symbols' => false, 'security.password_uncompromised' => false,
            'security.login_max_attempts' => 5, 'security.login_lockout_minutes' => 1, 'security.session_lifetime' => 120,
            'security.notify_lockout' => true, 'security.notify_new_ip' => true,
            'backups.schedule' => 'off', 'backups.include_files' => true, 'backups.retention_days' => 14,
            'integrations.recaptcha_enabled' => false, 'integrations.whatsapp_enabled' => true,
            'integrations.whatsapp_message' => 'Hello RTL, I would like some guidance.',
        ];
    }

    /** A saved value, or the built-in default. */
    public static function get(string $key, mixed $default = null): mixed
    {
        return app(SettingsRepository::class)->get($key, self::defaults()[$key] ?? $default);
    }

    /** @return list<string> one entry per non-blank line */
    public static function lines(string $key): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) self::get($key, '')) ?: [])));
    }

    public function __construct(private SettingsRepository $settings) {}

    /**
     * @param  list<string>  $allowed  groups the viewer may see
     * @return array{values: array<string, array<string, mixed>>, secrets: array<string, array<string, bool>>}
     */
    public function forAdmin(array $allowed): array
    {
        $values = [];
        $secrets = [];

        foreach (self::groups() as $group => $def) {
            if (! in_array($group, $allowed, true)) {
                continue;
            }

            foreach ($def['fields'] as $key => $field) {
                if ($field['type'] === 'secret') {
                    $values[$group][$key] = '';
                    $secrets[$group][$key] = filled($this->settings->get("{$group}.{$key}"));

                    continue;
                }

                $values[$group][$key] = $this->settings->get("{$group}.{$key}", self::defaults()["{$group}.{$key}"] ?? ($field['type'] === 'toggle' ? false : ''));
            }
        }

        return ['values' => $values, 'secrets' => $secrets];
    }

    /** @param array<string, mixed> $values validated */
    public function save(string $group, array $values): void
    {
        foreach (self::groups()[$group]['fields'] as $key => $field) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            if ($field['type'] === 'secret') {
                // Blank keeps the saved secret.
                if (filled($values[$key])) {
                    $this->settings->set("{$group}.{$key}", trim((string) $values[$key]), encrypted: true);
                }

                continue;
            }

            $value = match ($field['type']) {
                'toggle' => (bool) $values[$key],
                'number' => (int) $values[$key],
                default => $values[$key] === null ? '' : trim((string) $values[$key]),
            };

            $this->settings->set("{$group}.{$key}", $value, public: $group === 'integrations');
        }
    }

    /** @return array<string, list<mixed>> */
    public static function rules(string $group): array
    {
        $rules = [];

        foreach (self::groups()[$group]['fields'] as $key => $field) {
            $rules[$key] = match ($field['type']) {
                'toggle' => ['boolean'],
                'number' => ['required', 'integer', 'min:'.$field['min'], 'max:'.$field['max']],
                'datetime' => ['nullable', 'date'],
                'secret' => ['nullable', 'string', 'max:'.($field['max'] ?? 255)],
                'select' => ['required', 'in:'.implode(',', array_keys($field['options']))],
                default => ['nullable', 'string', 'max:'.($field['max'] ?? 255), ...(isset($field['pattern']) ? ['regex:'.$field['pattern']] : [])],
            };

            if (in_array($key, ['allow_ips', 'admin_ip_allowlist', 'blocked_ips'], true)) {
                $rules[$key][] = fn ($a, $v, $fail) => collect(preg_split('/\R/', (string) $v) ?: [])->map(fn (string $line) => trim($line))->filter()
                    ->contains(fn ($line) => ! IpMatcher::valid($line)) && $fail(__('Use one IP address or range (like 203.0.113.0/24) per line.'));
            }
        }

        return $rules;
    }
}
