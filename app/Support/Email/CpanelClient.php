<?php

namespace App\Support\Email;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the cPanel UAPI (https://api.docs.cpanel.net/cpanel/introduction/)
 * with an API token, so mailboxes can be managed from the admin panel and
 * staff can be signed in to webmail without seeing a mailbox password
 * (REQ §19.3).
 */
class CpanelClient
{
    public function __construct(private EmailSettings $settings) {}

    public function configured(): bool
    {
        return $this->settings->cpanelConfigured();
    }

    /**
     * Every domain on the cPanel account that can hold mailboxes.
     *
     * @return list<string>
     */
    public function domains(): array
    {
        $data = $this->call('DomainInfo', 'list_domains');

        return array_values(array_unique(array_filter([
            $data['main_domain'] ?? null,
            ...($data['addon_domains'] ?? []),
            ...($data['parked_domains'] ?? []),
            ...($data['sub_domains'] ?? []),
        ])));
    }

    /**
     * Mailboxes with their disk use, in MB.
     *
     * @return list<array{email: string, disk_used_mb: float, quota_mb: int|null, suspended: bool}>
     */
    public function mailboxes(): array
    {
        $rows = $this->call('Email', 'list_pops_with_disk');

        return array_values(array_map(fn (array $row) => [
            'email' => strtolower((string) $row['email']),
            'disk_used_mb' => round((float) ($row['diskused'] ?? 0), 2),
            'quota_mb' => is_numeric($row['diskquota'] ?? null) && (float) $row['diskquota'] > 0 ? (int) $row['diskquota'] : null,
            'suspended' => (bool) ($row['suspended_login'] ?? false),
        ], array_filter((array) $rows, fn ($row) => is_array($row) && isset($row['email']) && str_contains((string) $row['email'], '@'))));
    }

    public function createMailbox(string $localPart, string $domain, string $password, ?int $quotaMb): void
    {
        $this->call('Email', 'add_pop', ['email' => $localPart, 'domain' => $domain, 'password' => $password, 'quota' => $quotaMb ?? 0]);
    }

    public function deleteMailbox(string $localPart, string $domain): void
    {
        $this->call('Email', 'delete_pop', ['email' => $localPart, 'domain' => $domain]);
    }

    public function changePassword(string $localPart, string $domain, string $password): void
    {
        $this->call('Email', 'passwd_pop', ['email' => $localPart, 'domain' => $domain, 'password' => $password]);
    }

    public function setQuota(string $localPart, string $domain, ?int $quotaMb): void
    {
        $this->call('Email', 'edit_pop_quota', ['email' => $localPart, 'domain' => $domain, 'quota' => $quotaMb ?? 0]);
    }

    public function suspendLogin(string $email): void
    {
        $this->call('Email', 'suspend_login', ['email' => $email]);
    }

    public function unsuspendLogin(string $email): void
    {
        $this->call('Email', 'unsuspend_login', ['email' => $email]);
    }

    /**
     * A one-time webmail session for a mailbox. The browser posts `session`
     * to {webmail}{token}/login/ and lands signed in; no password is involved.
     *
     * @return array{session: string, token: string}
     */
    public function webmailSession(string $localPart, string $domain, ?string $remoteAddress): array
    {
        $data = $this->call('Session', 'create_webmail_session_for_mail_user', array_filter([
            'login' => $localPart,
            'domain' => $domain,
            'remote_address' => $remoteAddress,
        ]));

        if (! is_string($data['session'] ?? null) || ! is_string($data['token'] ?? null) || ! preg_match('#^/cpsess\d+$#', $data['token'])) {
            throw new CpanelException(__('cPanel did not return a webmail session.'));
        }

        return ['session' => $data['session'], 'token' => $data['token']];
    }

    /** Checks the host, user and token by asking for the account's domains. */
    public function test(): void
    {
        $this->domains();
    }

    /**
     * @param  array<string, scalar>  $params
     * @return mixed the "data" member of the UAPI response
     */
    private function call(string $module, string $function, array $params = []): mixed
    {
        if (! $this->configured()) {
            throw new CpanelException(__('The cPanel connection is not set up. Add it in Email settings.'));
        }

        $url = sprintf('https://%s:%d/execute/%s/%s', $this->settings->cpanelHost(), $this->settings->cpanelPort(), $module, $function);

        try {
            $response = Http::withHeaders(['Authorization' => 'cpanel '.$this->settings->cpanelUsername().':'.$this->settings->cpanelToken()])
                ->withOptions(['verify' => $this->settings->verifyTls()])
                ->acceptJson()
                ->timeout(20)
                ->asForm()
                ->post($url, $params);
        } catch (ConnectionException) {
            throw new CpanelException(__('Could not reach the cPanel server at :host.', ['host' => $this->settings->cpanelHost()]));
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new CpanelException(__('cPanel rejected the username or API token.'));
        }

        $json = $response->json();

        if (! $response->successful() || ! is_array($json)) {
            throw new CpanelException(__('cPanel answered with an unexpected response (HTTP :status).', ['status' => $response->status()]));
        }

        if ((int) ($json['status'] ?? 0) !== 1) {
            $errors = array_filter((array) ($json['errors'] ?? []), 'is_string');

            throw new CpanelException($errors !== [] ? implode(' ', $errors) : __('cPanel could not complete the request.'));
        }

        return $json['data'] ?? null;
    }
}
