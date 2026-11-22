<?php

namespace App\Support\Notifications;

use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Sends the email copy of a notification, but never lets a mail problem
 * (SMTP down, bad address) break the action that caused it, such as
 * confirming a payment. A failure is logged and the in-app copy still lands.
 */
class SafeMailChannel
{
    public function __construct(private readonly MailChannel $mail) {}

    public function send(object $notifiable, Notification $notification): void
    {
        try {
            $this->mail->send($notifiable, $notification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
