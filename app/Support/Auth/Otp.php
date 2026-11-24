<?php

namespace App\Support\Auth;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Six-digit email codes for verifying an address and resetting a password.
 *
 * - Codes come from a CSPRNG, live 5 minutes and work once.
 * - Only an HMAC (keyed with APP_KEY) is stored.
 * - 5 wrong guesses kill the code; a new one can be sent every 60 seconds.
 * - A password reset needs the code first, then a separate one-time grant.
 */
class Otp
{
    public const VERIFY_EMAIL = 'verify_email';

    public const PASSWORD_RESET = 'password_reset';

    /** Confirms an email address before a video consultation is booked (no account needed). */
    public const BOOKING = 'booking';

    public const TTL_MINUTES = 5;

    public const COOLDOWN_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    public const GRANT_MINUTES = 10;

    public const RESULT_OK = 'ok';

    public const RESULT_INVALID = 'invalid';

    public const RESULT_EXPIRED = 'expired';

    public const RESULT_LOCKED = 'locked';

    /** Seconds until another code may be requested (0 = now). Same answer for known and unknown emails. */
    public function cooldownRemaining(string $email, string $purpose): int
    {
        $until = (int) Cache::get($this->cooldownKey($email, $purpose), 0);

        return max(0, $until - time());
    }

    /**
     * Send a new code unless one went out in the last 60 seconds.
     * Pass no user for an unknown address: nothing is sent but the cooldown
     * still starts, so the response never reveals whether the address exists.
     *
     * A purpose that works for anyone (booking) passes $name instead of a user, and always sends.
     *
     * @return int seconds to wait (0 = a code was sent or pretended to be)
     */
    public function send(string $email, string $purpose, ?User $user, ?string $name = null): int
    {
        $email = Str::lower($email);

        if (($wait = $this->cooldownRemaining($email, $purpose)) > 0) {
            return $wait;
        }

        Cache::put($this->cooldownKey($email, $purpose), time() + self::COOLDOWN_SECONDS, self::COOLDOWN_SECONDS);
        Cache::forget($this->failKey($email, $purpose));

        if (! $user && $name === null) {
            return 0;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // A new code replaces every earlier one for the same purpose.
        OtpCode::where('email', $email)->where('purpose', $purpose)->delete();
        OtpCode::create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => $this->hash($code, $email, $purpose),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $mail = new OtpMail($user->name ?? (string) $name, $code, $purpose);
        $recipient = Mail::to($user->email ?? $email);

        // A booking code goes out now, not through the queue: nobody should wait for a queue worker
        // to pick up a code they are waiting to type. Other codes keep using the queue.
        $purpose === self::BOOKING ? $recipient->sendNow($mail) : $recipient->send($mail);

        return 0;
    }

    /** @return self::RESULT_* */
    public function verify(string $email, string $purpose, string $code): string
    {
        $email = Str::lower($email);

        // Counted per address whether or not an account exists, so the
        // limit (and the message) looks the same for everyone.
        if ((int) Cache::get($this->failKey($email, $purpose), 0) >= self::MAX_ATTEMPTS) {
            return self::RESULT_LOCKED;
        }

        $result = $this->check($email, $purpose, $code);

        if ($result === self::RESULT_OK) {
            Cache::forget($this->failKey($email, $purpose));
        } else {
            Cache::add($this->failKey($email, $purpose), 0, self::TTL_MINUTES * 60);
            Cache::increment($this->failKey($email, $purpose));
        }

        return $result;
    }

    /** One message for wrong, expired and unknown, so nothing reveals whether an account exists. */
    public static function message(string $result): string
    {
        return $result === self::RESULT_LOCKED
            ? __('Too many wrong attempts. Please request a new code.')
            : __('That code is incorrect or has expired. Check the code or request a new one.');
    }

    /** @return self::RESULT_* */
    private function check(string $email, string $purpose, string $code): string
    {
        $row = OtpCode::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $row) {
            return self::RESULT_INVALID;
        }

        if ($row->expires_at->isPast()) {
            return self::RESULT_EXPIRED;
        }

        if ($row->attempts >= self::MAX_ATTEMPTS) {
            return self::RESULT_LOCKED;
        }

        // Count the guess first, atomically, so parallel requests cannot dodge the limit.
        OtpCode::whereKey($row->id)->increment('attempts');

        if (! hash_equals($row->code_hash, $this->hash(trim($code), $email, $purpose))) {
            return $row->attempts + 1 >= self::MAX_ATTEMPTS ? self::RESULT_LOCKED : self::RESULT_INVALID;
        }

        // Single use: the row can only be consumed once.
        $consumed = OtpCode::whereKey($row->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        return $consumed === 1 ? self::RESULT_OK : self::RESULT_INVALID;
    }

    /** After a correct reset code: a one-time token the final password step must present. */
    public function grant(string $email): string
    {
        $email = Str::lower($email);
        $token = Str::random(64);

        OtpCode::where('email', $email)
            ->where('purpose', self::PASSWORD_RESET)
            ->whereNotNull('consumed_at')
            ->whereNull('grant_hash')
            ->latest('id')
            ->limit(1)
            ->update([
                'grant_hash' => hash('sha256', $token),
                'grant_expires_at' => now()->addMinutes(self::GRANT_MINUTES),
            ]);

        return $token;
    }

    /** Uses the grant up. True only the first time, before it expires. */
    public function redeemGrant(string $email, string $token): bool
    {
        return OtpCode::where('email', Str::lower($email))
            ->where('purpose', self::PASSWORD_RESET)
            ->where('grant_hash', hash('sha256', $token))
            ->whereNull('grant_used_at')
            ->where('grant_expires_at', '>', now())
            ->update(['grant_used_at' => now()]) === 1;
    }

    /** Whether the grant is still good (checked before showing the new-password form). */
    public function grantValid(string $email, string $token): bool
    {
        return OtpCode::where('email', Str::lower($email))
            ->where('purpose', self::PASSWORD_RESET)
            ->where('grant_hash', hash('sha256', $token))
            ->whereNull('grant_used_at')
            ->where('grant_expires_at', '>', now())
            ->exists();
    }

    public function forget(string $email, string $purpose): void
    {
        OtpCode::where('email', Str::lower($email))->where('purpose', $purpose)->delete();
        Cache::forget($this->cooldownKey($email, $purpose));
        Cache::forget($this->failKey($email, $purpose));
    }

    private function hash(string $code, string $email, string $purpose): string
    {
        return hash_hmac('sha256', "{$purpose}|{$email}|{$code}", (string) config('app.key'));
    }

    private function failKey(string $email, string $purpose): string
    {
        return 'otp-fail:'.$purpose.':'.sha1(Str::lower($email));
    }

    private function cooldownKey(string $email, string $purpose): string
    {
        return 'otp-cooldown:'.$purpose.':'.sha1(Str::lower($email));
    }
}
