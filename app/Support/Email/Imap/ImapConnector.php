<?php

namespace App\Support\Email\Imap;

use App\Models\EmailAccount;
use App\Support\Email\EmailSettings;

/**
 * Opens an IMAP connection to a cPanel mailbox using the server settings and
 * the mailbox password that are already stored (encrypted) for sending.
 * Tests replace this class in the container with a fake.
 */
class ImapConnector
{
    public function __construct(private EmailSettings $settings) {}

    public function ready(EmailAccount $account): bool
    {
        return $account->isActive() && $account->on_server && filled($account->password) && $this->settings->imap()['host'] !== '';
    }

    /** @throws ImapException */
    public function connect(EmailAccount $account): MailboxConnection
    {
        if (! $this->ready($account)) {
            throw new ImapException(__('This mailbox is not set up for receiving yet (it needs a password and the IMAP server).'));
        }

        $imap = $this->settings->imap();

        return new WebklexConnection([
            'host' => $imap['host'],
            'port' => $imap['port'],
            'encryption' => $imap['encryption'],
            'validate_cert' => $this->settings->verifyTls(),
            'username' => $account->email,
            'password' => (string) $account->password,
            'protocol' => 'imap',
            'authentication' => null,
            'timeout' => 25,
        ]);
    }
}
