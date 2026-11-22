<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Support\Email\CpanelClient;
use App\Support\Email\CpanelException;
use App\Support\Email\EmailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** REQ §19.2 Email Domain / IMAP / SMTP Configuration, §23.3 SMTP / Mail Server. */
class EmailSettingsController extends Controller
{
    private const HOST = 'regex:/^(?!-)[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/';

    public function edit(EmailSettings $settings): Response
    {
        Gate::authorize('email.manage');

        return Inertia::render('admin/email/Settings', [
            'settings' => $settings->forAdmin(),
            'webmailUrl' => $settings->webmailUrl(),
            // Mailboxes to try the connection tests with. Never the passwords themselves.
            'mailboxes' => EmailAccount::query()->orderBy('email')->get()->map(fn (EmailAccount $a) => [
                'id' => $a->id,
                'email' => $a->email,
                'login_name' => $a->loginName(),
                'has_password' => filled($a->getRawOriginal('password')),
            ])->all(),
        ]);
    }

    public function update(Request $request, EmailSettings $settings): RedirectResponse
    {
        Gate::authorize('email.manage');

        $data = $request->validate([
            'cpanel_host' => ['nullable', 'string', 'max:255', self::HOST],
            'cpanel_port' => ['required', 'integer', 'between:1,65535'],
            'cpanel_username' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_.-]+$/i'],
            'cpanel_token' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9]+$/'],
            'clear_cpanel_token' => ['boolean'],
            'verify_tls' => ['required', 'boolean'],
            'domains' => ['present', 'array', 'max:50'],
            'domains.*' => ['string', 'max:255', 'distinct:ignore_case', self::HOST],
            'default_quota_mb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'webmail_url' => ['nullable', 'url:https', 'max:255'],
            'smtp_host' => ['nullable', 'string', 'max:255', self::HOST],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', Rule::in(['ssl', 'tls'])],
            'imap_host' => ['nullable', 'string', 'max:255', self::HOST],
            'imap_port' => ['required', 'integer', 'between:1,65535'],
            'imap_encryption' => ['nullable', Rule::in(['ssl', 'tls'])],
            'tracking_enabled' => ['required', 'boolean'],
        ], [
            'cpanel_host.regex' => __('Enter the server name only, e.g. server.example.com, without https:// or a port.'),
            'cpanel_token.regex' => __('A cPanel API token is letters and numbers only. Create one in cPanel → Security → Manage API Tokens.'),
            'domains.*.regex' => __('Each domain should look like example.com.'),
        ]);

        $tokenChanged = filled($data['cpanel_token'] ?? null) || ($data['clear_cpanel_token'] ?? false);
        $settings->save($data);

        activity('email')->causedBy($request->user())->withProperties([
            'cpanel_host' => $data['cpanel_host'] ?? null,
            'token_changed' => $tokenChanged,
        ])->log('Email settings updated');

        $this->toast(['type' => 'success', 'message' => __('Email settings saved.')]);

        return back();
    }

    /** Checks the saved cPanel connection and offers the domains it reports. */
    public function test(Request $request, CpanelClient $cpanel): RedirectResponse
    {
        Gate::authorize('email.manage');

        try {
            $domains = $cpanel->domains();
        } catch (CpanelException $e) {
            throw ValidationException::withMessages(['cpanel' => $e->getMessage()]);
        }

        $this->toast(['type' => 'success', 'message' => __('Connected to cPanel. Domains on this account: :domains', ['domains' => implode(', ', $domains) ?: __('none')])]);

        return back()->with('cpanelDomains', $domains);
    }
}
