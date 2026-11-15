<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Secure account invitation (REQ §11.2): a signed, expiring link that lets
 * a user created by staff choose their own password. No password is ever
 * sent by email.
 */
class AccountInvitation extends Notification
{
    use Queueable;

    public const VALID_HOURS = 72;

    public function __construct(public readonly ?string $invitedBy = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'invitation.accept',
            now()->addHours(self::VALID_HOURS),
            ['user' => $notifiable->getKey(), 'hash' => sha1($notifiable->email)],
        );

        $site = config('app.name');

        return (new MailMessage)
            ->subject(__('Your :site account is ready', ['site' => $site]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($this->invitedBy
                ? __(':inviter has created an account for you on :site.', ['inviter' => $this->invitedBy, 'site' => $site])
                : __('An account has been created for you on :site.', ['site' => $site]))
            ->action(__('Set your password'), $url)
            ->line(__('This link expires in :hours hours.', ['hours' => self::VALID_HOURS]))
            ->line(__('If you were not expecting this email, you can ignore it.'));
    }
}
