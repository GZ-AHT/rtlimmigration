<?php

namespace App\Support\System;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\SecurityAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/** Tells Super Admins and security managers about suspicious sign-in activity (REQ §23.2). */
class SecurityAlerts
{
    /** Sends once per kind and subject within the window, so a flood of attempts is one notification. */
    public static function send(string $kind, string $subject, string $title, string $body, int $minutes = 30): void
    {
        if (! Cache::add('security:alert:'.$kind.':'.md5($subject), true, now()->addMinutes($minutes))) {
            return;
        }

        $recipients = User::query()
            ->where('status', UserStatus::Active->value)
            ->where(fn (Builder $q) => $q->permission('security.manage')->orWhereHas('roles', fn (Builder $r) => $r->where('name', Role::SuperAdmin->value)))
            ->get();

        Notification::send($recipients, new SecurityAlert($title, $body));
    }
}
