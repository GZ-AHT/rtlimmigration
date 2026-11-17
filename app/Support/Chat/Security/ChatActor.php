<?php

namespace App\Support\Chat\Security;

use App\Models\ChatGuest;
use App\Models\User;

/** Who is sending: a signed-in user (student or staff) or a website guest, and from which IP address. */
final readonly class ChatActor
{
    public function __construct(
        public ?User $user,
        public ?ChatGuest $guest,
        public ?string $ip,
        public bool $staff = false,
    ) {}

    public static function user(User $user, ?string $ip, bool $staff): self
    {
        return new self($user, null, $ip, $staff);
    }

    public static function guest(ChatGuest $guest, ?string $ip): self
    {
        return new self(null, $guest, $ip);
    }

    /** Rate limits and cooldowns are counted per account or per guest session, not per IP alone. */
    public function key(): string
    {
        return $this->guest !== null ? "guest:{$this->guest->id}" : 'user:'.($this->user->id ?? 0);
    }
}
