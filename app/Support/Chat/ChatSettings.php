<?php

namespace App\Support\Chat;

use App\Enums\Area;
use App\Models\User;
use App\Support\Settings\SettingsRepository;

/**
 * Tawk.to connection settings (REQ §18, §23.3 "Live Chat Integration"),
 * kept in the settings store under the "chat" group. The two secrets are
 * encrypted at rest and never leave the server: the browser only receives
 * the public widget IDs and a per-visitor hash.
 */
class ChatSettings
{
    public const DASHBOARD_URL = 'https://dashboard.tawk.to';

    /** Tawk.to allows at most 10 tags per chat. */
    public const MAX_TAGS = 10;

    /** The route Tawk.to posts to (routes/web.php). A custom address must still end with it. */
    public const WEBHOOK_PATH = '/chat/webhook/tawk';

    /** Each deployment has its own webhook address; this server uses the one for its APP_ENV. */
    public const WEBHOOK_ENVIRONMENTS = ['local', 'staging', 'production'];

    /** Environments Tawk.to reaches over the internet, so their address must be public HTTPS. */
    public const PUBLIC_ENVIRONMENTS = ['staging', 'production'];

    public function __construct(private SettingsRepository $settings) {}

    /** The widget can only load once it is switched on and both IDs are set. */
    public function enabled(): bool
    {
        return (bool) $this->get('enabled', false) && filled($this->get('property_id')) && filled($this->get('widget_id'));
    }

    /** Secure Mode: visitors we identify are signed with the property's API key. */
    public function secureMode(): bool
    {
        return filled($this->get('api_key'));
    }

    public function hasWebhookSecret(): bool
    {
        return filled($this->get('webhook_secret'));
    }

    /**
     * Values for the admin settings form. Secrets are reported as set or
     * not set, never returned.
     *
     * @return array{enabled: bool, property_id: string, widget_id: string, show_on_website: bool, show_in_portal: bool, portal_tags: list<string>, has_api_key: bool, has_webhook_secret: bool, webhook_urls: array<string, string>}
     */
    public function forAdmin(): array
    {
        return [
            'enabled' => (bool) $this->get('enabled', false),
            'property_id' => (string) $this->get('property_id', ''),
            'widget_id' => (string) $this->get('widget_id', ''),
            'show_on_website' => (bool) $this->get('show_on_website', true),
            'show_in_portal' => (bool) $this->get('show_in_portal', true),
            'portal_tags' => $this->portalTags(),
            'has_api_key' => $this->secureMode(),
            'has_webhook_secret' => $this->hasWebhookSecret(),
            'webhook_urls' => $this->webhookUrls(),
        ];
    }

    /** Which webhook address applies to this server: local, staging or production. */
    public static function currentEnvironment(): string
    {
        return match (true) {
            app()->isProduction() => 'production',
            app()->environment('staging') => 'staging',
            default => 'local',
        };
    }

    /**
     * The saved address per environment; blank means "use this server's
     * own route".
     *
     * @return array<string, string>
     */
    public function webhookUrls(): array
    {
        $saved = $this->get('webhook_urls', []);
        $urls = [];

        foreach (self::WEBHOOK_ENVIRONMENTS as $env) {
            $value = is_array($saved) ? ($saved[$env] ?? null) : null;
            $urls[$env] = is_string($value) ? $value : '';
        }

        return $urls;
    }

    /** The address to paste into Tawk.to for this server. */
    public function webhookUrl(): string
    {
        return $this->webhookUrls()[self::currentEnvironment()] ?: route('chat.webhook');
    }

    /**
     * Why a webhook address cannot be used for an environment, or null when
     * it is fine. It must point at this app's webhook route, carry no
     * credentials or query, and be public HTTPS for staging and production.
     */
    public static function webhookUrlProblem(string $url, string $environment): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return __('Enter a full address starting with https://.');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return __('The address cannot contain a username, password, query string or #fragment.');
        }

        if (! str_ends_with(rtrim($parts['path'] ?? '', '/'), self::WEBHOOK_PATH)) {
            return __('The address must end with :path, the route that receives Tawk.to webhooks.', ['path' => self::WEBHOOK_PATH]);
        }

        if (in_array($environment, self::PUBLIC_ENVIRONMENTS, true)) {
            if (strtolower($parts['scheme']) !== 'https') {
                return __('Staging and production addresses must use https://.');
            }

            $host = strtolower(trim($parts['host'], '[]'));

            $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
            $isPublicIp = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

            if ($host === 'localhost' || str_ends_with($host, '.localhost') || ($isIp && ! $isPublicIp)) {
                return __('Tawk.to cannot reach a local or private address. Use the public domain.');
            }
        }

        return null;
    }

    /**
     * @param  array{enabled: bool, property_id: ?string, widget_id: ?string, show_on_website: bool, show_in_portal: bool, portal_tags: list<string>, api_key?: ?string, webhook_secret?: ?string, clear_api_key?: bool, clear_webhook_secret?: bool, webhook_urls?: array<string, ?string>}  $data
     */
    public function save(array $data): void
    {
        foreach (['enabled', 'show_on_website', 'show_in_portal'] as $flag) {
            $this->settings->set("chat.{$flag}", (bool) $data[$flag], public: false);
        }

        $this->settings->set('chat.property_id', trim((string) ($data['property_id'] ?? '')), public: false);
        $this->settings->set('chat.widget_id', trim((string) ($data['widget_id'] ?? '')), public: false);
        $this->settings->set('chat.portal_tags', $data['portal_tags'], public: false);

        // Encrypted at rest like the secrets, so a database dump does not
        // reveal the deployment addresses. Left out of a request = unchanged.
        if (array_key_exists('webhook_urls', $data)) {
            $urls = [];

            foreach (self::WEBHOOK_ENVIRONMENTS as $env) {
                $urls[$env] = trim((string) ($data['webhook_urls'][$env] ?? ''));
            }

            $this->settings->set('chat.webhook_urls', $urls, encrypted: true);
        }

        // A blank secret field means "keep what is stored".
        foreach (['api_key', 'webhook_secret'] as $secret) {
            if ($data["clear_{$secret}"] ?? false) {
                $this->settings->set("chat.{$secret}", null, encrypted: true);
            } elseif (filled($data[$secret] ?? null)) {
                $this->settings->set("chat.{$secret}", trim((string) $data[$secret]), encrypted: true);
            }
        }
    }

    /**
     * What the browser needs to load the widget for this visitor, or null
     * when it should not show: chat is off, the audience is switched off,
     * or the user is staff (they answer chats in Tawk.to, not through the
     * visitor widget).
     *
     * @return array{propertyId: string, widgetId: string, visitor: array{name: string, email: string, hash: string|null}|null, attributes: array<string, string>, tags: list<string>}|null
     */
    public function widgetFor(?User $user): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $base = [
            'propertyId' => (string) $this->get('property_id'),
            'widgetId' => (string) $this->get('widget_id'),
            'visitor' => null,
            'attributes' => [],
            'tags' => [],
        ];

        if ($user === null) {
            return $this->get('show_on_website', true) ? $base : null;
        }

        if ($user->homeArea() !== Area::Portal || ! $this->get('show_in_portal', true)) {
            return null;
        }

        $student = $user->student;
        $file = $student?->files()->first();

        return [
            ...$base,
            'visitor' => ['name' => $user->name, 'email' => $user->email, 'hash' => $this->visitorHash($user->email)],
            // REQ §18 "Student ID / File Number as Custom Attributes".
            // Tawk.to: keys are letters, digits and "-"; values are non-empty, at most 255 characters.
            'attributes' => array_map(fn (string $v) => mb_substr($v, 0, 255), array_filter([
                'student-no' => (string) $student?->student_number,
                'file-no' => (string) $file?->file_number,
                'counselor' => (string) $student?->counselor?->name,
            ], fn (string $v) => $v !== '')),
            'tags' => $this->portalTags(),
        ];
    }

    /** Tawk.to Secure Mode: HMAC-SHA256 of the visitor's email with the property API key. */
    public function visitorHash(string $email): ?string
    {
        $key = $this->get('api_key');

        return filled($key) ? hash_hmac('sha256', $email, (string) $key) : null;
    }

    /** Tawk.to signs each webhook: X-Tawk-Signature is the HMAC-SHA1 of the raw body. */
    public function signatureIsValid(string $rawBody, ?string $signature): bool
    {
        $secret = $this->get('webhook_secret');

        if (blank($secret) || blank($signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha1', $rawBody, (string) $secret), (string) $signature);
    }

    /** @return list<string> */
    public function portalTags(): array
    {
        $tags = $this->get('portal_tags', ['student']);

        return is_array($tags) ? array_values(array_filter($tags, 'is_string')) : [];
    }

    private function get(string $key, mixed $default = null): mixed
    {
        return $this->settings->get("chat.{$key}", $default);
    }
}
