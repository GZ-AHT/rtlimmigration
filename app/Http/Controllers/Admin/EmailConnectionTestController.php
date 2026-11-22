<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Support\Email\ConnectionTester;
use App\Support\Email\Imap\ImapException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Email → Settings: "Test IMAP connection" and "Test SMTP connection".
 * They try the values in the form (saved or not) with a chosen mailbox's own
 * username and stored password, and never send or change anything.
 */
class EmailConnectionTestController extends Controller
{
    private const HOST = 'regex:/^(?!-)[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/';

    public function imap(Request $request, ConnectionTester $tester): RedirectResponse
    {
        [$account, $server] = $this->input($request, ['ssl', 'tls']);
        $server['encryption'] = $server['encryption'] === 'tls' ? 'starttls' : 'ssl';

        try {
            $result = $tester->imap($account, $server);
        } catch (ImapException $e) {
            $this->log($request, $account, 'IMAP', false);

            throw ValidationException::withMessages(['imap_test' => $e->getMessage()]);
        }

        $this->log($request, $account, 'IMAP', true);
        $this->toast(['type' => 'success', 'message' => $result]);

        return back();
    }

    public function smtp(Request $request, ConnectionTester $tester): RedirectResponse
    {
        [$account, $server] = $this->input($request, ['ssl', 'tls']);

        try {
            $result = $tester->smtp($account, $server);
        } catch (\RuntimeException $e) {
            $this->log($request, $account, 'SMTP', false);

            throw ValidationException::withMessages(['smtp_test' => $e->getMessage()]);
        }

        $this->log($request, $account, 'SMTP', true);
        $this->toast(['type' => 'success', 'message' => $result]);

        return back();
    }

    /**
     * @param  list<string>  $encryptions
     * @return array{0: EmailAccount, 1: array{host: string, port: int, encryption: string}}
     */
    private function input(Request $request, array $encryptions): array
    {
        Gate::authorize('email.manage');

        $data = $request->validate([
            'email_account_id' => ['required', 'integer', Rule::exists(EmailAccount::class, 'id')],
            'host' => ['required', 'string', 'max:255', self::HOST],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in($encryptions)],
        ], [
            'host.regex' => __('Enter the server name only, e.g. mail.example.com, without https:// or a port.'),
        ]);

        // An administrator-only tool, but it still must not become a way to probe the internal network.
        if (filter_var($data['host'], FILTER_VALIDATE_IP) !== false && filter_var($data['host'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw ValidationException::withMessages(['host' => __('Use the mail server\'s public name, not an internal address.')]);
        }

        return [
            EmailAccount::findOrFail((int) $data['email_account_id']),
            ['host' => strtolower($data['host']), 'port' => (int) $data['port'], 'encryption' => $data['encryption']],
        ];
    }

    private function log(Request $request, EmailAccount $account, string $kind, bool $ok): void
    {
        activity('email')->causedBy($request->user())->performedOn($account)
            ->withProperties(['email' => $account->email, 'result' => $ok ? 'ok' : 'failed'])
            ->log("Tested the {$kind} connection");
    }
}
