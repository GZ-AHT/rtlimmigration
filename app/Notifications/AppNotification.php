<?php

namespace App\Notifications;

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

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
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
