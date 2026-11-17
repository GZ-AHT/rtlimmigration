<?php

namespace App\Support\Chat;

use App\Support\Settings\SettingsRepository;

/**
 * Chat security settings, kept in the settings store under the
 * "chat_security" group and edited by Super Admin (security.manage).
 * None of these are public: the browser only receives what a page needs,
 * such as the Turnstile site key and the composer limits.
 */
class ChatSecuritySettings
{
    /** Attachment types a Super Admin can switch on. Chat is text-only until they do. */
    public const ATTACHMENT_TYPES = [
        'pdf' => ['label' => 'PDF', 'extensions' => ['pdf'], 'mimes' => ['application/pdf']],
        'jpg' => ['label' => 'JPG / JPEG', 'extensions' => ['jpg', 'jpeg'], 'mimes' => ['image/jpeg']],
        'png' => ['label' => 'PNG', 'extensions' => ['png'], 'mimes' => ['image/png']],
    ];

    public const CAPTCHA_MODES = ['off', 'suspicious', 'always'];

    /**
     * Defaults and allowed range for each number.
     *
     * @var array<string, array{0: int, 1: int, 2: int}> default, min, max
     */
    public const LIMITS = [
        'messages_per_minute' => [5, 1, 120],
        'messages_per_hour' => [30, 1, 2000],
        'ip_messages_per_minute' => [20, 1, 600],
        'conversation_messages_per_minute' => [20, 1, 600],
        'guest_conversations_per_hour' => [3, 1, 100],
        'duplicate_window_seconds' => [60, 0, 3600],
        'repeat_threshold' => [3, 2, 50],
        'violations_before_cooldown' => [3, 1, 50],
        'cooldown_minutes' => [15, 1, 1440],
        'auto_block_after_cooldowns' => [3, 0, 50],
        'auto_block_hours' => [24, 1, 8760],
        'max_attachment_kb' => [5120, 100, 20480],
        'max_files' => [3, 1, 10],
        'retention_days' => [90, 7, 3650],
        'guest_session_days' => [30, 1, 365],
        'alert_cooldown_minutes' => [30, 1, 1440],
    ];

    /** Keywords and domains: at most this many entries each. */
    public const MAX_LIST = 500;

    public function __construct(private SettingsRepository $settings) {}

    public function int(string $key): int
    {
        [$default, $min, $max] = self::LIMITS[$key];
        $value = $this->settings->get("chat_security.{$key}", $default);

        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }

    public function guestChatEnabled(): bool
    {
        return (bool) $this->settings->get('chat_security.guest_chat_enabled', false);
    }

    public function captchaMode(): string
    {
        $mode = $this->settings->get('chat_security.captcha_mode', 'suspicious');

        return in_array($mode, self::CAPTCHA_MODES, true) ? $mode : 'suspicious';
    }

    public function turnstileSiteKey(): string
    {
        return (string) $this->settings->get('chat_security.turnstile_site_key', '');
    }

    public function turnstileSecret(): string
    {
        return (string) $this->settings->get('chat_security.turnstile_secret', '');
    }

    /** CAPTCHA can only be asked for once both Turnstile keys are saved. */
    public function captchaAvailable(): bool
    {
        return $this->captchaMode() !== 'off' && $this->turnstileSiteKey() !== '' && $this->turnstileSecret() !== '';
    }

    /** @return list<string> keys of ATTACHMENT_TYPES that are switched on */
    public function allowedAttachmentTypes(): array
    {
        $saved = $this->settings->get('chat_security.attachment_types', []);

        return array_values(array_filter(array_keys(self::ATTACHMENT_TYPES), fn (string $t) => is_array($saved) && in_array($t, $saved, true)));
    }

    public function attachmentsEnabled(): bool
    {
        return $this->allowedAttachmentTypes() !== [];
    }

    /** @return array<string, list<string>> allowed extension => MIME types (from the file's contents) */
    public function allowedExtensions(): array
    {
        $result = [];

        foreach ($this->allowedAttachmentTypes() as $type) {
            foreach (self::ATTACHMENT_TYPES[$type]['extensions'] as $ext) {
                $result[$ext] = self::ATTACHMENT_TYPES[$type]['mimes'];
            }
        }

        return $result;
    }

    /** @return list<string> lower-case, trimmed */
    public function blockedKeywords(): array
    {
        return $this->list('blocked_keywords');
    }

    /** @return list<string> */
    public function blockedDomains(): array
    {
        return $this->list('blocked_domains');
    }

    /**
     * Domains whose links are never flagged (e.g. universities, embassies, our own site).
     *
     * @return list<string>
     */
    public function trustedDomains(): array
    {
        return $this->list('trusted_domains');
    }

    /**
     * Everything for the Super Admin form. The Turnstile secret is
     * reported as set or not set, never returned.
     *
     * @return array<string, mixed>
     */
    public function forAdmin(): array
    {
        $limits = [];

        foreach (array_keys(self::LIMITS) as $key) {
            $limits[$key] = $this->int($key);
        }

        return [
            'guest_chat_enabled' => $this->guestChatEnabled(),
            'limits' => $limits,
            'attachment_types' => $this->allowedAttachmentTypes(),
            'blocked_keywords' => $this->blockedKeywords(),
            'blocked_domains' => $this->blockedDomains(),
            'trusted_domains' => $this->trustedDomains(),
            'captcha_mode' => $this->captchaMode(),
            'turnstile_site_key' => $this->turnstileSiteKey(),
            'has_turnstile_secret' => $this->turnstileSecret() !== '',
        ];
    }

    /** @param  array<string, mixed>  $data  validated by ChatSecurityController */
    public function save(array $data): void
    {
        $this->settings->set('chat_security.guest_chat_enabled', (bool) $data['guest_chat_enabled'], public: false);

        foreach (array_keys(self::LIMITS) as $key) {
            $this->settings->set("chat_security.{$key}", (int) $data['limits'][$key], public: false);
        }

        $this->settings->set('chat_security.attachment_types', array_values(array_intersect(array_keys(self::ATTACHMENT_TYPES), $data['attachment_types'])), public: false);

        foreach (['blocked_keywords', 'blocked_domains', 'trusted_domains'] as $list) {
            $this->settings->set("chat_security.{$list}", self::cleanList($data[$list], $list !== 'blocked_keywords'), public: false);
        }

        $this->settings->set('chat_security.captcha_mode', $data['captcha_mode'], public: false);
        $this->settings->set('chat_security.turnstile_site_key', trim((string) ($data['turnstile_site_key'] ?? '')), public: false);

        if ($data['clear_turnstile_secret'] ?? false) {
            $this->settings->set('chat_security.turnstile_secret', null, encrypted: true);
        } elseif (filled($data['turnstile_secret'] ?? null)) {
            $this->settings->set('chat_security.turnstile_secret', trim((string) $data['turnstile_secret']), encrypted: true);
        }
    }

    /**
     * Trims, lower-cases and de-duplicates a list. Domains also lose any
     * scheme, path and leading "www." or "*.".
     *
     * @param  list<string>  $items
     * @return list<string>
     */
    public static function cleanList(array $items, bool $domains): array
    {
        $clean = [];

        foreach ($items as $item) {
            $item = mb_strtolower(trim((string) $item));

            if ($domains) {
                $item = (string) preg_replace('~^[a-z][a-z0-9+.-]*://~', '', $item);
                $item = (string) preg_replace('~[/?#:].*$~', '', $item);
                $item = (string) preg_replace('~^(\*\.|www\.)~', '', $item);
                $item = trim($item, '.');
            }

            if ($item !== '' && mb_strlen($item) <= 120) {
                $clean[$item] = true;
            }
        }

        return array_slice(array_keys($clean), 0, self::MAX_LIST);
    }

    /** @return list<string> */
    private function list(string $key): array
    {
        $value = $this->settings->get("chat_security.{$key}", []);

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
