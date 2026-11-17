<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * A chat is open while it is going on and closed once Tawk.to reports it
 * ended. An offline message is open until a member of staff marks it handled.
 * A portal thread is open until staff mark it resolved; the student writing
 * again reopens it.
 */
enum ChatStatus: string
{
    use HasOptions;

    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
        };
    }

    public function labelFor(ChatKind $kind): string
    {
        return match (true) {
            $kind === ChatKind::Chat && $this === self::Open => 'In progress',
            $kind === ChatKind::Chat => 'Ended',
            $kind->isNative() && $this === self::Open => 'Open',
            $kind->isNative() => 'Resolved',
            $this === self::Open => 'Needs reply',
            default => 'Handled',
        };
    }

    public function toneFor(ChatKind $kind): string
    {
        return match (true) {
            $kind === ChatKind::Chat && $this === self::Open => 'info',
            $kind === ChatKind::Offline && $this === self::Open => 'warning',
            $kind->isNative() && $this === self::Open => 'success',
            default => 'neutral',
        };
    }
}
