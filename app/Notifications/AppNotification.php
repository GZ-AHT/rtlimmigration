<?php

namespace App\Notifications;

use App\Support\Notifications\NotificationPreferences;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base class for in-app notifications (REQ §20). Every notification stored
 * in the database uses the same shape so the notification centre can render
 * any of them:
 *
 *   title     short headline
 *   body      one-line detail
 *   url       where clicking the notification goes (relative), or null
 *   icon      Material Symbols name
 *   category  system | student | counselor | payment | application
 */
abstract class AppNotification extends Notification
{
    abstract protected function title(object $notifiable): string;

    abstract protected function body(object $notifiable): string;

    protected function url(object $notifiable): ?string
    {
        return null;
    }

    protected function icon(): string
    {
        return 'notifications';
    }

    protected function category(): string
    {
        return 'system';
    }

    /**
     * In-app by default; email too when the person's role or the person has
     * asked for it (see NotificationPreferences).
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return app(NotificationPreferences::class)->channels($notifiable, static::class, $this->category());
    }

    /** Email copy, worded by the notification template if an admin wrote one. */
    public function toMail(object $notifiable): MailMessage
    {
        $template = app(NotificationPreferences::class)->template(static::class);
        $path = $this->url($notifiable);
        $link = $path !== null ? url($path) : '';
        $values = [
            '{name}' => (string) ($notifiable->name ?? ''),
            '{title}' => $this->title($notifiable),
            '{body}' => $this->body($notifiable),
            '{url}' => $link,
            '{app}' => (string) config('app.name'),
        ];
        $subject = trim(strtr($template['email_subject'] !== '' ? $template['email_subject'] : '{title}', $values));
        $text = strtr($template['email_body'] !== '' ? $template['email_body'] : '{body}', $values);

        $mail = (new MailMessage)->subject($subject)->greeting(__('Hello :name,', ['name' => $values['{name}']]));

        foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $paragraph) {
            $mail->line($paragraph);
        }

        return $link !== '' ? $mail->action(__('Open'), $link) : $mail;
    }

    /** @return array{title: string, body: string, url: string|null, icon: string, category: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title($notifiable),
            'body' => $this->body($notifiable),
            'url' => $this->url($notifiable),
            'icon' => $this->icon(),
            'category' => $this->category(),
        ];
    }
}
