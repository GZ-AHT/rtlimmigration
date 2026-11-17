<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What a Tawk.to webhook told us about (a live chat, or a message left
 * while offline), a student's portal message thread with staff, or a
 * website visitor's chat with staff (kept in this system, like portal
 * threads).
 */
enum ChatKind: string
{
    use HasOptions;

    case Chat = 'chat';
    case Offline = 'offline';
    case Portal = 'portal';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Chat => 'Live chat',
            self::Offline => 'Offline message',
            self::Portal => 'Portal messages',
            self::Guest => 'Website chat',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Chat => 'forum',
            self::Offline => 'mark_email_unread',
            self::Portal => 'chat',
            self::Guest => 'travel_explore',
        };
    }

    /** Threads whose messages are stored here and answered from the dashboard. */
    public function isNative(): bool
    {
        return $this === self::Portal || $this === self::Guest;
    }
}
