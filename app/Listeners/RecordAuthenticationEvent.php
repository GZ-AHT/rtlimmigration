<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
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

        $this->record('login', $event->user->id, $event->user->email);

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
