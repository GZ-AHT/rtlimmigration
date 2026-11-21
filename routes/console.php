<?php

use Illuminate\Support\Facades\Schedule;

// REQ §7 / §8.1: remind counselors when lead follow-ups fall due.
// Requires the scheduler: `* * * * * php artisan schedule:run` in cron.
Schedule::command('leads:remind-follow-ups')->everyFifteenMinutes()->withoutOverlapping();

// REQ §12.4: flag unpaid requests as overdue once their due date passes.
Schedule::command('payments:mark-overdue')->dailyAt('00:10')->withoutOverlapping();

// REQ §11 I: chat security data retention.
Schedule::command('chat:prune-security')->dailyAt('02:30')->withoutOverlapping();

// REQ §22.3: weekly broken-link scan of pages, posts and menus.
Schedule::command('seo:check-links')->weeklyOn(1, '03:30')->withoutOverlapping();

// REQ §23.5: automatic backups, as chosen in System → Settings → Backups.
Schedule::command('backup:run --scheduled')->dailyAt('02:00')->withoutOverlapping();
