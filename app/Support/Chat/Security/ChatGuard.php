<?php

namespace App\Support\Chat\Security;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\ChatConversation;
use App\Models\ChatGuest;
use App\Models\ChatIpBlock;
use App\Models\ChatMessage;
use App\Models\ChatSecurityEvent;
use App\Models\User;
use App\Notifications\ChatSecurityAlert;
use App\Support\Chat\ChatSecuritySettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Chat spam protection: blocked IPs, suspended guests, per-sender, per-IP
 * and per-conversation message limits, cooldowns after repeated
 * violations, duplicate and repeated messages, suspicion tracking for
 * CAPTCHA, the security log and Super Admin alerts.
 *
 * Staff are not rate limited here (their routes keep Laravel's throttle).
 * Counters live in the cache; blocks and the log live in the database.
 */
class ChatGuard
{
    /** How long a visitor stays "suspicious" (CAPTCHA required) after a warning sign. */
    private const SUSPICION_MINUTES = 60;

    /** Passing a CAPTCHA covers this long, so a visitor is not asked on every message. */
    private const CAPTCHA_PASS_MINUTES = 30;

    /** Same message sent this many times within the window counts as repeated spam. */
    private const REPEAT_WINDOW_SECONDS = 600;

    public function __construct(private ChatSecuritySettings $settings) {}

    /**
     * May this sender post now? Throws ChatSecurityException with a clear
     * message when not, and counts the attempt when they may.
     */
    public function admit(ChatActor $actor, ChatConversation $conversation): void
    {
        if ($actor->staff) {
            return;
        }

        $this->ensureNotBlocked($actor, $conversation);

        $key = $actor->key();
        $cooldown = "chat:cooldown:{$key}";

        $until = Cache::get($cooldown);

        if (is_int($until)) {
            $left = max(1, $until - time());

            throw new ChatSecurityException(429, __('You have sent too many messages. You can write again in :minutes minutes.', ['minutes' => intdiv($left + 59, 60)]), $left);
        }

        $limits = [
            "chat:msg:m:{$key}" => [$this->settings->int('messages_per_minute'), 60],
            "chat:msg:h:{$key}" => [$this->settings->int('messages_per_hour'), 3600],
            "chat:msg:conv:{$conversation->id}" => [$this->settings->int('conversation_messages_per_minute'), 60],
        ];

        if ($actor->ip !== null) {
            $limits['chat:msg:ip:'.$actor->ip] = [$this->settings->int('ip_messages_per_minute'), 60];
        }

        foreach ($limits as $limiterKey => [$max, $decay]) {
            if (RateLimiter::tooManyAttempts($limiterKey, $max)) {
                $this->violation($actor, $conversation, $limiterKey, RateLimiter::availableIn($limiterKey));
            }
        }

        foreach ($limits as $limiterKey => [, $decay]) {
            RateLimiter::hit($limiterKey, $decay);
        }
    }

    /** Blocked IP address or suspended guest: refused before anything else. */
    public function ensureNotBlocked(ChatActor $actor, ?ChatConversation $conversation = null): void
    {
        if ($actor->staff) {
            return;
        }

        if ($actor->ip !== null && $this->ipBlocked($actor->ip)) {
            $this->recordOnce($actor, 'blocked_ip', 'warning', $conversation);

            throw new ChatSecurityException(403, __('Chat is not available from your network. Please contact us by email or phone.'));
        }

        if ($actor->guest?->isSuspended()) {
            throw new ChatSecurityException(403, __('This chat has been paused. Please contact us by email or phone.'));
        }
    }

    public function ipBlocked(string $ip): bool
    {
        return ChatIpBlock::query()->active()->where('ip_address', $ip)->exists();
    }

    /**
     * A second copy of the sender's last message within the duplicate
     * window (a double click, or a resend with a new ID) is refused with a
     * clear error rather than stored twice.
     */
    public function rejectDuplicate(ChatActor $actor, ChatConversation $conversation, ?string $body): void
    {
        $window = $this->settings->int('duplicate_window_seconds');

        if ($actor->staff || $body === null || $window === 0) {
            return;
        }

        $last = $conversation->chatMessages()
            ->when($actor->guest !== null,
                fn (Builder $q) => $q->where('sender_type', ChatMessage::VISITOR),
                fn (Builder $q) => $q->where('user_id', $actor->user->id ?? 0))
            ->where('created_at', '>=', now()->subSeconds($window))
            ->latest('id')
            ->value('body');

        if ($last !== null && self::fingerprint($last) === self::fingerprint($body)) {
            $this->record($actor, 'duplicate', 'info', $conversation);

            throw new ChatSecurityException(422, __('You just sent this message. It has been received.'));
        }
    }

    /** How many times this sender has sent this text in the last ten minutes, including now. */
    public function repeatCount(ChatActor $actor, string $body): int
    {
        $key = 'chat:repeat:'.$actor->key().':'.self::fingerprint($body);
        Cache::add($key, 0, self::REPEAT_WINDOW_SECONDS);

        return (int) Cache::increment($key);
    }

    public function repeatThreshold(): int
    {
        return $this->settings->int('repeat_threshold');
    }

    /** Text compared without case, spacing or punctuation differences. */
    public static function fingerprint(string $text): string
    {
        return md5((string) preg_replace('~[^\p{L}\p{N}]+~u', '', ContentInspector::normalise($text)));
    }

    /* ------------------------------------------------------------------
     | Guests
     * ---------------------------------------------------------------- */

    /** Limits how many website chats one IP address can start per hour. */
    public function admitGuestStart(?string $ip, ChatActor $actor): void
    {
        $key = 'chat:guest-start:'.($ip ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, $this->settings->int('guest_conversations_per_hour'))) {
            $this->record($actor, 'conversation_limit', 'warning');
            $this->markSuspicious($ip);
            $this->alert('conversation_limit', (string) $ip, __('Many website chats from one address'), __('An IP address tried to start more than :n website chats in an hour.', ['n' => $this->settings->int('guest_conversations_per_hour')]));

            throw new ChatSecurityException(429, __('Too many chats have been started from your network. Please try again later or contact us by email.'), RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, 3600);
    }

    public function markSuspicious(?string $ip, ?ChatGuest $guest = null): void
    {
        if ($ip !== null) {
            Cache::put("chat:suspicious:ip:{$ip}", true, now()->addMinutes(self::SUSPICION_MINUTES));
        }

        if ($guest !== null) {
            Cache::put("chat:suspicious:guest:{$guest->id}", true, now()->addMinutes(self::SUSPICION_MINUTES));
        }
    }

    /**
     * Whether a CAPTCHA is needed before this guest action: always (when
     * set so) when starting a chat, otherwise only after a warning sign,
     * and never when Turnstile is not set up.
     */
    public function captchaRequired(?string $ip, ?ChatGuest $guest, bool $starting): bool
    {
        if (! $this->settings->captchaAvailable()) {
            return false;
        }

        if ($guest !== null && Cache::has("chat:captcha-ok:guest:{$guest->id}")) {
            return false;
        }

        if ($starting && $this->settings->captchaMode() === 'always') {
            return true;
        }

        return ($ip !== null && Cache::has("chat:suspicious:ip:{$ip}"))
            || ($guest !== null && Cache::has("chat:suspicious:guest:{$guest->id}"));
    }

    public function captchaPassed(ChatGuest $guest): void
    {
        Cache::put("chat:captcha-ok:guest:{$guest->id}", true, now()->addMinutes(self::CAPTCHA_PASS_MINUTES));
        Cache::forget("chat:suspicious:guest:{$guest->id}");
    }

    public function suspendGuest(ChatGuest $guest, string $reason, ?int $hours, ?User $by = null): void
    {
        $guest->forceFill([
            'suspended_until' => now()->addHours($hours ?? 24 * 365 * 10),
            'suspension_reason' => mb_substr($reason, 0, 255),
        ])->save();

        $this->record(new ChatActor($by, $guest, $guest->ip_address), 'guest_suspended', 'warning', $guest->conversation, null, ['reason' => $reason, 'by' => $by?->id]);
    }

    public function blockIp(string $ip, string $reason, ?int $hours, ?User $by = null, bool $automatic = false): ChatIpBlock
    {
        $block = ChatIpBlock::query()->updateOrCreate(['ip_address' => $ip], [
            'reason' => mb_substr($reason, 0, 255),
            'automatic' => $automatic,
            'expires_at' => $hours !== null ? now()->addHours($hours) : null,
            'created_by' => $by?->id,
        ]);

        $this->record(new ChatActor($by, null, $ip), 'ip_blocked', 'critical', null, null, ['reason' => $reason, 'automatic' => $automatic, 'hours' => $hours]);

        return $block;
    }

    /* ------------------------------------------------------------------
     | Log and alerts
     * ---------------------------------------------------------------- */

    /** @param  array<string, mixed>  $details  never message text or secrets */
    public function record(ChatActor $actor, string $type, string $severity, ?ChatConversation $conversation = null, ?ChatMessage $message = null, array $details = []): ChatSecurityEvent
    {
        return ChatSecurityEvent::query()->create([
            'type' => $type,
            'severity' => $severity,
            'ip_address' => $actor->ip,
            'user_id' => $actor->user?->id,
            'chat_guest_id' => $actor->guest?->id,
            'chat_conversation_id' => $conversation?->id,
            'chat_message_id' => $message?->id,
            'details' => $details ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * Notifies Super Admins (and anyone with security.manage), at most
     * once per kind and subject within the alert window, so a flood of
     * events is one notification.
     */
    public function alert(string $kind, string $subject, string $title, string $body, string $target = 'activity'): void
    {
        if (! Cache::add('chat:alert:'.$kind.':'.md5($subject), true, now()->addMinutes($this->settings->int('alert_cooldown_minutes')))) {
            return;
        }

        $recipients = User::query()
            ->where('status', UserStatus::Active->value)
            ->where(fn (Builder $q) => $q->permission('security.manage')->orWhereHas('roles', fn (Builder $r) => $r->where('name', Role::SuperAdmin->value)))
            ->get();

        Notification::send($recipients, new ChatSecurityAlert($title, $body, $target));
    }

    /** Who to name in an alert, without exposing more than needed. */
    public function describe(ChatActor $actor): string
    {
        return $actor->guest !== null
            ? __('Website visitor ":name"', ['name' => $actor->guest->name])
            : ($actor->user->name ?? __('A user'));
    }

    /* ------------------------------------------------------------------
     | Internals
     * ---------------------------------------------------------------- */

    private function violation(ChatActor $actor, ChatConversation $conversation, string $limiterKey, int $retryAfter): never
    {
        $key = $actor->key();
        $violationsKey = "chat:violations:{$key}";
        Cache::add($violationsKey, 0, 3600);
        $violations = (int) Cache::increment($violationsKey);

        $this->record($actor, 'rate_limited', 'info', $conversation, null, ['limit' => explode(':', $limiterKey)[2] ?? 'limit', 'violations' => $violations]);
        $this->markSuspicious($actor->guest ? $actor->ip : null, $actor->guest);

        if ($violations < $this->settings->int('violations_before_cooldown')) {
            throw new ChatSecurityException(429, __('You are sending messages too quickly. Wait :seconds seconds and try again.', ['seconds' => max(1, $retryAfter)]), max(1, $retryAfter));
        }

        // Repeated violations: a cooldown, and an alert.
        $minutes = $this->settings->int('cooldown_minutes');
        Cache::put("chat:cooldown:{$key}", time() + $minutes * 60, now()->addMinutes($minutes));
        Cache::forget($violationsKey);

        $cooldownsKey = "chat:cooldowns:{$key}";
        Cache::add($cooldownsKey, 0, 86400);
        $cooldowns = (int) Cache::increment($cooldownsKey);

        $this->record($actor, 'cooldown', 'warning', $conversation, null, ['minutes' => $minutes, 'cooldowns_today' => $cooldowns]);
        $this->alert('cooldown', $key, __('Chat message limit exceeded'), __(':who kept exceeding the message limit and was paused for :minutes minutes.', ['who' => $this->describe($actor), 'minutes' => $minutes]));

        if ($cooldowns >= 2 && $actor->user !== null) {
            $this->alert('repeat_offender', $key, __('Account repeatedly breaking chat rules'), __(':who has been paused :n times today.', ['who' => $this->describe($actor), 'n' => $cooldowns]));
        }

        // Guests who keep at it lose the session, and their IP is blocked
        // for a while. Students are never blocked automatically: shared
        // networks (universities, offices) would lock out others.
        $threshold = $this->settings->int('auto_block_after_cooldowns');

        if ($actor->guest !== null && $threshold > 0 && $cooldowns >= $threshold) {
            $hours = $this->settings->int('auto_block_hours');
            $this->suspendGuest($actor->guest, __('Automatic: repeated message flooding'), $hours);

            if ($actor->ip !== null && ! $this->ipBlocked($actor->ip)) {
                $this->blockIp($actor->ip, __('Automatic: repeated message flooding from a website visitor'), $hours, null, true);
                $this->alert('auto_block', $actor->ip, __('IP address blocked automatically'), __('A website visitor kept flooding the chat. Their address is blocked for :hours hours.', ['hours' => $hours]));
            }
        }

        throw new ChatSecurityException(429, __('You have sent too many messages. You can write again in :minutes minutes.', ['minutes' => $minutes]), $minutes * 60);
    }

    /** One log entry per actor and type per minute, for refusals that repeat on every request. */
    private function recordOnce(ChatActor $actor, string $type, string $severity, ?ChatConversation $conversation): void
    {
        if (Cache::add("chat:logged:{$type}:".$actor->key().':'.$actor->ip, true, 60)) {
            $this->record($actor, $type, $severity, $conversation);
        }
    }
}
