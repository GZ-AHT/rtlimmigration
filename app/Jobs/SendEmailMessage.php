<?php

namespace App\Jobs;

use App\Models\EmailMessage;
use App\Support\Email\EmailSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Sends one logged email (REQ §3). Retries a few times for SMTP hiccups. */
class SendEmailMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $messageId) {}

    public function handle(EmailSender $sender): void
    {
        $message = EmailMessage::with('account')->find($this->messageId);

        if ($message === null) {
            return;
        }

        // A sync queue has no retries, so its first attempt is also its last.
        $final = $this->attempts() >= $this->tries || $this->job?->getConnectionName() === 'sync';

        $sender->deliver($message, finalAttempt: $final);
    }
}
