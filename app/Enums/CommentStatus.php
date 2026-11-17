<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** REQ §4 Blog Comments: every comment waits for approval before it is shown. */
enum CommentStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Spam = 'spam';
    case Rejected = 'rejected';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Spam, self::Rejected => 'danger',
        };
    }
}
