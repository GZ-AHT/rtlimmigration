<?php

namespace App\Support\Email;

use App\Support\Settings\SettingsRepository;

/**
 * Mail server settings (REQ §19.2 "Email Domain / IMAP / SMTP
 * Configuration", §23.3 "SMTP / Mail Server"), kept in the settings store
 * under the "email" group. The cPanel API token is encrypted and never
 * returned to the browser.
 */
class EmailSettings
{
    public function __construct(private SettingsRepository $settings) {}

    public function cpanelConfigured(): bool
    {
        return filled($this->get('cpanel_host')) && filled($this->get('cpanel_username')) && filled($this->get('cpanel_token'));
    }

    public function cpanelHost(): string
    {
        return (string) $this->get('cpanel_host', '');
    }

    public function cpanelPort(): int
    {
        return (int) $this->get('cpanel_port', 2083);
    }

    public function cpanelUsername(): string
    {
        return (string) $this->get('cpanel_username', '');
    }

    public function cpanelToken(): string
    {
        return (string) $this->get('cpanel_token', '');
    }

    /** Hosts with a self-signed certificate (common on a fresh server) can switch this off. */
    public function verifyTls(): bool
    {
        return (bool) $this->get('verify_tls', true);
    }

    /** @return list<string> */
    public function domains(): array
    {
        return array_values(array_filter(array_map('strval', (array) $this->get('domains', []))));
    }

    public function defaultQuotaMb(): ?int
    {
        $quota = $this->get('default_quota_mb', 1024);

        return $quota === null || (int) $quota === 0 ? null : (int) $quota;
    }

    /** Where Roundcube is reached: cPanel webmail runs on port 2096 of the server. */
    public function webmailUrl(): string
    {
        $url = (string) $this->get('webmail_url', '');

        if ($url !== '') {
            return rtrim($url, '/');
        }

        return $this->cpanelHost() !== '' ? 'https://'.$this->cpanelHost().':2096' : '';
    }

    /**
     * SMTP server used to send as a mailbox.
     *
     * @return array{host: string, port: int, encryption: string}
     */
    public function smtp(): array
    {
        return [
            'host' => (string) ($this->get('smtp_host') ?: $this->cpanelHost()),
            'port' => (int) $this->get('smtp_port', 465),
            'encryption' => (string) $this->get('smtp_encryption', 'ssl'),
        ];
    }

    /** @return array{host: string, port: int, encryption: string} */
    public function imap(): array
    {
        $port = (int) $this->get('imap_port', 993);

        return [
            'host' => (string) ($this->get('imap_host') ?: $this->cpanelHost()),
            'port' => $port,
            // 993 is IMAP over TLS; the usual alternative, 143, upgrades with STARTTLS.
            'encryption' => $port === 143 ? 'starttls' : 'ssl',
        ];
    }

    public function trackingEnabled(): bool
    {
        return (bool) $this->get('tracking_enabled', true);
    }

    /** @return array<string, mixed> */
    public function forAdmin(): array
    {
        return [
            'cpanel_host' => $this->cpanelHost(),
            'cpanel_port' => $this->cpanelPort(),
            'cpanel_username' => $this->cpanelUsername(),
            'has_cpanel_token' => filled($this->get('cpanel_token')),
            'verify_tls' => $this->verifyTls(),
            'domains' => $this->domains(),
            'default_quota_mb' => $this->defaultQuotaMb(),
            'webmail_url' => (string) $this->get('webmail_url', ''),
            'smtp_host' => (string) $this->get('smtp_host', ''),
            'smtp_port' => (int) $this->get('smtp_port', 465),
            'smtp_encryption' => (string) $this->get('smtp_encryption', 'ssl'),
            'imap_host' => (string) $this->get('imap_host', ''),
            'imap_port' => (int) $this->get('imap_port', 993),
            'tracking_enabled' => $this->trackingEnabled(),
        ];
    }

    /** @param array<string, mixed> $data validated form input */
    public function save(array $data): void
    {
        foreach (['cpanel_host', 'cpanel_username', 'webmail_url', 'smtp_host', 'imap_host', 'smtp_encryption'] as $key) {
            $this->settings->set("email.{$key}", trim((string) ($data[$key] ?? '')));
        }

        foreach (['cpanel_port', 'smtp_port', 'imap_port'] as $key) {
            $this->settings->set("email.{$key}", (int) $data[$key]);
        }

        $this->settings->set('email.default_quota_mb', isset($data['default_quota_mb']) ? (int) $data['default_quota_mb'] : null);
        $this->settings->set('email.domains', array_values(array_unique(array_map(fn ($d) => strtolower(trim((string) $d)), $data['domains'] ?? []))));
        $this->settings->set('email.verify_tls', (bool) $data['verify_tls']);
        $this->settings->set('email.tracking_enabled', (bool) $data['tracking_enabled']);

        if ($data['clear_cpanel_token'] ?? false) {
            $this->settings->set('email.cpanel_token', null, encrypted: true);
        } elseif (filled($data['cpanel_token'] ?? null)) {
            $this->settings->set('email.cpanel_token', trim((string) $data['cpanel_token']), encrypted: true);
        }
    }

    private function get(string $key, mixed $default = null): mixed
    {
        return $this->settings->get("email.{$key}", $default);
    }
}
