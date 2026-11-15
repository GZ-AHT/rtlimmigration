<?php

use Illuminate\Support\Facades\Schedule;

// REQ §7 / §8.1: remind counselors when lead follow-ups fall due.
// Requires the scheduler: `* * * * * php artisan schedule:run` in cron.
Schedule::command('leads:remind-follow-ups')->everyFifteenMinutes()->withoutOverlapping();

// REQ §12.4: flag unpaid requests as overdue once their due date passes.
Schedule::command('payments:mark-overdue')->dailyAt('00:10')->withoutOverlapping();
