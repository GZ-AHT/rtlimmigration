<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Models\User;
use App\Support\System\SecurityAlerts;
use App\Support\System\SystemSettings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

/**
 * Writes the login history (REQ §6, §23.2). Passwords are never stored;
 * failed attempts keep only the email that was typed.
 */
class RecordAuthenticationEvent
{
    public function __construct(private Request $request) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        // REQ §23.2: tell security managers when staff sign in from an address they haven't used before.
        $ip = $this->request->ip();
        $isStaff = $event->user->canAny(['area.admin', 'area.counselor', 'area.accounts']);
        $seenBefore = LoginHistory::query()->where('user_id', $event->user->id)->where('event', 'login')->where('ip_address', $ip)->exists();

        $this->record('login', $event->user->id, $event->user->email);

        if ($isStaff && ! $seenBefore && $ip !== null && SystemSettings::get('security.notify_new_ip')
            && LoginHistory::query()->where('user_id', $event->user->id)->where('event', 'login')->count() > 1) {
            SecurityAlerts::send('new-ip', $event->user->id.'|'.$ip, __('Staff sign-in from a new address'), "{$event->user->name} signed in from {$ip}.", 1440);
        }

        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $this->request->ip(),
        ])->saveQuietly();
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;
        $email = is_string($email) ? mb_substr($email, 0, 255) : null;

        // Fortify fires Failed without the user when our credential check
        // returns null, so link the attempt to the account by email.
        $userId = $event->user instanceof User
            ? $event->user->id
            : ($email !== null ? User::where('email', $email)->value('id') : null);

        $this->record('failed', $userId, $email);

        // The sign-in throttle answers with 429 without raising Lockout, so count the failures here:
        // reaching the attempt limit is the lockout.
        $ip = $this->request->ip();
        $window = (int) SystemSettings::get('security.login_lockout_minutes', 1);
        $recent = LoginHistory::query()->where('event', 'failed')->where('ip_address', $ip)->where('email', $email)->where('created_at', '>=', now()->subMinutes($window))->count();

        if ($recent >= (int) SystemSettings::get('security.login_max_attempts', 5) && SystemSettings::get('security.notify_lockout')) {
            SecurityAlerts::send('lockout', $email.'|'.$ip, __('Sign-in lockout'), __('Too many sign-in attempts for :email from :ip.', ['email' => mb_substr((string) $email, 0, 120), 'ip' => $ip]));
        }
    }

    public function handleLockout(Lockout $event): void
    {
        $email = (string) $event->request->input('email');
        $ip = $event->request->ip();

        if (SystemSettings::get('security.notify_lockout')) {
            SecurityAlerts::send('lockout', $email.'|'.$ip, __('Sign-in lockout'), __('Too many sign-in attempts for :email from :ip.', ['email' => mb_substr($email, 0, 120), 'ip' => $ip]));
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->record('logout', $event->user->id, $event->user->email);
        }
    }

    private function record(string $event, ?int $userId, ?string $email): void
    {
        LoginHistory::create([
            'user_id' => $userId,
            'email' => $email,
            'event' => $event,
            'ip_address' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 512),
        ]);
    }
}
