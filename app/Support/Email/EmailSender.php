<?php

namespace App\Support\Email;

use App\Enums\EmailMessageStatus;
use App\Jobs\SendEmailMessage;
use App\Mail\OutgoingEmail;
use App\Models\EmailAccount;
use App\Models\EmailCampaign;
use App\Models\EmailMessage;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends mail as a cPanel mailbox through its SMTP server, or through the
 * app's default mailer when no mailbox is chosen. Every email is logged
 * first and sent from the queue (REQ §3 "Bulk Email", "Delivery Logs").
 */
class EmailSender
{
    public function __construct(private EmailSettings $settings) {}

    /** @param array<string, mixed> $attributes EmailMessage columns */
    public function queue(array $attributes): EmailMessage
    {
        $account = isset($attributes['email_account_id']) ? EmailAccount::find((int) $attributes['email_account_id']) : null;

        $message = EmailMessage::create([
            ...$attributes,
            'uuid' => (string) Str::uuid(),
            'from_email' => $account->email ?? (string) config('mail.from.address'),
            'from_name' => $account?->fromName() ?? (string) config('mail.from.name'),
            'status' => EmailMessageStatus::Queued,
        ]);

        SendEmailMessage::dispatch($message->id)->afterCommit();

        return $message;
    }

    /** Called by the job. Records the outcome; rethrows before the last attempt so the queue retries. */
    public function deliver(EmailMessage $message, bool $finalAttempt): void
    {
        if ($message->status === EmailMessageStatus::Sent) {
            return;
        }

        try {
            $this->mailerFor($message->account)->send(new OutgoingEmail($message));
        } catch (Throwable $e) {
            if (! $finalAttempt) {
                $message->update(['error' => Str::limit($e->getMessage(), 500)]);

                throw $e;
            }

            report($e);
            $this->record($message, EmailMessageStatus::Failed, Str::limit($e->getMessage(), 500));

            return;
        }

        $this->record($message, EmailMessageStatus::Sent);
    }

    /** Sends a test message synchronously, so the admin sees an SMTP error immediately. */
    public function sendTest(EmailAccount $account, string $to): void
    {
        $message = new EmailMessage([
            'uuid' => (string) Str::uuid(),
            'from_email' => $account->email,
            'from_name' => $account->fromName(),
            'to_email' => $to,
            'subject' => __('Test email from :app', ['app' => config('app.name')]),
            'body' => '<p>'.e(__('This mailbox can send email from :app.', ['app' => config('app.name')])).'</p>',
        ]);

        $this->mailerFor($account)->send(new OutgoingEmail($message));
    }

    /** A mailbox's SMTP connection, registered as its own mailer so Mail::fake() still applies. */
    public function mailerFor(?EmailAccount $account): Mailer
    {
        if ($account === null) {
            return Mail::mailer();
        }

        if (! $account->canSendSmtp()) {
            throw new \RuntimeException(__('The mailbox :email cannot send: it is suspended or has no saved password.', ['email' => $account->email]));
        }

        $smtp = $this->settings->smtp();
        $name = 'mailbox-'.$account->id;

        config(["mail.mailers.{$name}" => [
            'transport' => 'smtp',
            'scheme' => $smtp['encryption'] === 'ssl' ? 'smtps' : 'smtp',
            'host' => $smtp['host'],
            'port' => $smtp['port'],
            'username' => $account->email,
            'password' => $account->password,
            'timeout' => 30,
        ]]);

        // The password or server may have changed since this worker last used the mailer.
        Mail::purge($name);

        return Mail::mailer($name);
    }

    private function record(EmailMessage $message, EmailMessageStatus $status, ?string $error = null): void
    {
        DB::transaction(function () use ($message, $status, $error) {
            $message->update([
                'status' => $status,
                'error' => $error,
                'sent_at' => $status === EmailMessageStatus::Sent ? now() : null,
            ]);

            if ($message->email_campaign_id !== null) {
                EmailCampaign::whereKey($message->email_campaign_id)->increment($status === EmailMessageStatus::Sent ? 'sent' : 'failed');
            }
        });
    }
}
