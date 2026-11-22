<?php

namespace App\Support\Email;

use App\Models\EmailAccount;
use App\Support\Email\Imap\ImapConnector;
use App\Support\Email\Imap\ImapException;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Throwable;

/**
 * "Test connection" for the mail server settings: signs in as a mailbox with
 * the settings being tried (before they are saved) and reports what it found.
 * Nothing is sent or changed on the server, and the password never appears
 * in a result or a log: error text is scrubbed of it.
 */
class ConnectionTester
{
    public function __construct(private ImapConnector $imap, private EmailSettings $settings) {}

    /**
     * @param  array{host: string, port: int, encryption: string}  $server  encryption: ssl | starttls
     * @return string what was found
     *
     * @throws ImapException
     */
    public function imap(EmailAccount $account, array $server): string
    {
        try {
            $connection = $this->imap->connect($account, $server);
        } catch (ImapException $e) {
            throw new ImapException($this->scrub($e->getMessage(), $account), previous: $e);
        }

        try {
            $roles = array_keys($connection->folders());
        } catch (ImapException $e) {
            throw new ImapException($this->scrub($e->getMessage(), $account), previous: $e);
        } finally {
            $connection->close();
        }

        return __('Signed in to :host as :user. Folders found: :folders.', [
            'host' => $server['host'],
            'user' => $account->loginName(),
            'folders' => $roles === [] ? __('none of Inbox, Drafts, Spam or Trash') : implode(', ', array_map('ucfirst', $roles)),
        ]);
    }

    /**
     * Opens the SMTP session and signs in, then closes it. No email is sent.
     *
     * @param  array{host: string, port: int, encryption: string}  $server  encryption: ssl | tls (STARTTLS)
     * @return string what was found
     *
     * @throws \RuntimeException
     */
    public function smtp(EmailAccount $account, array $server): string
    {
        if (blank($account->password)) {
            throw new \RuntimeException(__('This mailbox has no saved password yet.'));
        }

        $transport = $this->transport($server['host'], $server['port'], $server['encryption'] === 'ssl');
        $transport->setUsername($account->loginName());
        $transport->setPassword((string) $account->password);

        $stream = $transport->getStream();

        if (! $this->settings->verifyTls() && $stream instanceof SocketStream) {
            $stream->setStreamOptions(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
        }

        try {
            $transport->start();
        } catch (Throwable $e) {
            throw new \RuntimeException($this->scrub($e->getMessage(), $account), previous: $e);
        } finally {
            try {
                $transport->stop();
            } catch (Throwable) {
                // The session never opened.
            }
        }

        return __('Signed in to :host:port as :user. The mail server accepts this mailbox for sending.', [
            'host' => $server['host'],
            'port' => ':'.$server['port'],
            'user' => $account->loginName(),
        ]);
    }

    /** Overridden in tests. */
    protected function transport(string $host, int $port, bool $ssl): EsmtpTransport
    {
        return new EsmtpTransport($host, $port, $ssl, null, null);
    }

    /** Server replies can echo a login; never pass the password or username through. */
    private function scrub(string $message, EmailAccount $account): string
    {
        foreach ([(string) $account->password, $account->loginName(), $account->email] as $secret) {
            if ($secret !== '') {
                $message = str_ireplace($secret, '•••', $message);
            }
        }

        return Str::limit(trim((string) preg_replace('/\s+/', ' ', $message)), 300);
    }
}
