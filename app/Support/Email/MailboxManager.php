<?php

namespace App\Support\Email;

use App\Enums\EmailAccountStatus;
use App\Models\EmailAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mailbox lifecycle (REQ §19.2): every change is made on the cPanel server
 * first, when the connection is set up, and recorded here only if cPanel
 * accepted it. Without a connection, accounts are recorded only, so they can
 * be assigned now and created on the server later.
 */
class MailboxManager
{
    public function __construct(private CpanelClient $cpanel) {}

    /**
     * @param  array{local_part: string, domain: string, display_name?: string|null, password?: string|null, quota_mb?: int|null}  $data
     */
    public function create(array $data, User $actor): EmailAccount
    {
        $local = strtolower(trim($data['local_part']));
        $domain = strtolower(trim($data['domain']));
        $password = filled($data['password'] ?? null) ? (string) $data['password'] : self::generatePassword();
        $quota = $data['quota_mb'] ?? null;

        $onServer = $this->cpanel->configured();

        if ($onServer) {
            $this->cpanel->createMailbox($local, $domain, $password, $quota);
        }

        $account = EmailAccount::create([
            'email' => "{$local}@{$domain}",
            'local_part' => $local,
            'domain' => $domain,
            'display_name' => $data['display_name'] ?? null,
            // Record-only accounts keep a password only if the admin typed the real one.
            'password' => $onServer || filled($data['password'] ?? null) ? $password : null,
            'quota_mb' => $quota,
            'status' => EmailAccountStatus::Active,
            'on_server' => $onServer,
            'created_by' => $actor->id,
        ]);

        $this->log($actor, $account, $onServer ? 'Mailbox created on the mail server' : 'Mailbox recorded (not on the mail server)');

        return $account;
    }

    /** Creates a recorded account on the server once cPanel is connected. */
    public function provision(EmailAccount $account, User $actor): void
    {
        $password = $account->password ?: self::generatePassword();
        $this->cpanel->createMailbox($account->local_part, $account->domain, $password, $account->quota_mb);

        $account->update(['password' => $password, 'on_server' => true]);
        $this->log($actor, $account, 'Mailbox created on the mail server');
    }

    public function changePassword(EmailAccount $account, string $password, User $actor): void
    {
        if ($account->on_server) {
            $this->cpanel->changePassword($account->local_part, $account->domain, $password);
        }

        $account->update(['password' => $password]);
        $this->log($actor, $account, $account->on_server ? 'Mailbox password changed' : 'Saved mailbox password updated');
    }

    public function setQuota(EmailAccount $account, ?int $quotaMb, User $actor): void
    {
        if ($account->quota_mb === $quotaMb) {
            return;
        }

        if ($account->on_server) {
            $this->cpanel->setQuota($account->local_part, $account->domain, $quotaMb);
        }

        $account->update(['quota_mb' => $quotaMb]);
        $this->log($actor, $account, 'Mailbox limit changed', ['quota_mb' => $quotaMb]);
    }

    public function setStatus(EmailAccount $account, EmailAccountStatus $status, User $actor): void
    {
        if ($account->status === $status) {
            return;
        }

        if ($account->on_server) {
            $status === EmailAccountStatus::Suspended
                ? $this->cpanel->suspendLogin($account->email)
                : $this->cpanel->unsuspendLogin($account->email);
        }

        $account->update(['status' => $status]);
        $this->log($actor, $account, $status === EmailAccountStatus::Suspended ? 'Mailbox suspended' : 'Mailbox resumed');
    }

    /**
     * Deleting from the server erases the mail in it. Admins can instead
     * remove only our record and leave the mailbox on the server.
     */
    public function delete(EmailAccount $account, bool $fromServer, User $actor): void
    {
        if ($fromServer && $account->on_server) {
            $this->cpanel->deleteMailbox($account->local_part, $account->domain);
        }

        $this->log($actor, $account, $fromServer && $account->on_server ? 'Mailbox deleted from the mail server' : 'Mailbox record removed');
        $account->delete();
    }

    /**
     * Who may use a mailbox. `can_send` lets them pick it as From when
     * sending from this system; webmail access comes with any assignment.
     *
     * @param  array<int, bool>  $assignments  user id => can send
     */
    public function assign(EmailAccount $account, array $assignments, User $actor): void
    {
        $before = $account->users()->pluck('users.id')->all();

        $account->users()->sync(collect($assignments)->mapWithKeys(fn (bool $canSend, int $userId) => [
            $userId => ['can_send' => $canSend, 'assigned_by' => $actor->id],
        ])->all());

        $after = array_keys($assignments);
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        if ($added !== [] || $removed !== []) {
            $this->log($actor, $account, 'Mailbox access changed', ['added_users' => $added, 'removed_users' => $removed]);
        }
    }

    /**
     * Pulls mailboxes, usage and suspension from cPanel. Mailboxes made in
     * cPanel directly are imported (without a password, so they can be used
     * in webmail but not sent from here until one is saved). Run by hand
     * (an actor) or on a schedule (no actor).
     *
     * @return array{imported: int, updated: int, missing: int}
     */
    public function sync(?User $actor): array
    {
        $remote = collect($this->cpanel->mailboxes())->keyBy('email');
        $result = ['imported' => 0, 'updated' => 0, 'missing' => 0];

        DB::transaction(function () use ($remote, &$result) {
            foreach ($remote as $email => $box) {
                $account = EmailAccount::where('email', $email)->first();
                [$local, $domain] = explode('@', $email, 2);

                $attributes = [
                    'disk_used_mb' => $box['disk_used_mb'],
                    'quota_mb' => $box['quota_mb'],
                    'status' => $box['suspended'] ? EmailAccountStatus::Suspended : EmailAccountStatus::Active,
                    'on_server' => true,
                    'synced_at' => now(),
                ];

                if ($account === null) {
                    EmailAccount::create([...$attributes, 'email' => $email, 'local_part' => $local, 'domain' => $domain]);
                    $result['imported']++;
                } else {
                    $account->update($attributes);
                    $result['updated']++;
                }
            }

            // Accounts we think are on the server but cPanel no longer lists.
            $missing = EmailAccount::where('on_server', true)->whereNotIn('email', $remote->keys()->all())->get();
            foreach ($missing as $account) {
                $account->update(['on_server' => false, 'synced_at' => now()]);
            }
            $result['missing'] = $missing->count();
        });

        activity('email')->causedBy($actor)->withProperties($result)->log('Mailboxes synced from the mail server');

        return $result;
    }

    public static function generatePassword(): string
    {
        // cPanel rates passwords by strength; mixed classes and length keep it well above the default minimum.
        return Str::password(24);
    }

    /** @param array<string, mixed> $properties */
    private function log(User $actor, EmailAccount $account, string $description, array $properties = []): void
    {
        activity('email')->causedBy($actor)->performedOn($account)->withProperties(['email' => $account->email, ...$properties])->log($description);
    }
}
