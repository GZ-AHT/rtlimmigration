<?php

namespace App\Console\Commands;

use App\Models\ChatGuest;
use App\Models\ChatIpBlock;
use App\Models\ChatSecurityEvent;
use App\Support\Chat\ChatSecuritySettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * REQ §11 I data retention: removes security events older than the
 * retention period and expired IP blocks, ends idle guest sessions and
 * drops their IP address and browser details. Moderation history and the
 * activity log are audit records and are kept.
 */
#[Signature('chat:prune-security')]
#[Description('Apply the chat security data retention periods')]
class PruneChatSecurity extends Command
{
    public function handle(ChatSecuritySettings $settings): int
    {
        $events = ChatSecurityEvent::query()->where('created_at', '<', now()->subDays($settings->int('retention_days')))->delete();
        $blocks = ChatIpBlock::query()->whereNotNull('expires_at')->where('expires_at', '<', now())->delete();

        $guests = 0;

        ChatGuest::query()
            ->where('last_seen_at', '<', now()->subDays($settings->int('guest_session_days')))
            ->where(fn ($q) => $q->whereNotNull('ip_address')->orWhereNotNull('user_agent'))
            ->chunkById(200, function ($chunk) use (&$guests) {
                foreach ($chunk as $guest) {
                    // A new random hash: the old cookie no longer opens the chat.
                    $guest->forceFill(['token_hash' => ChatGuest::hashToken(Str::random(64)), 'ip_address' => null, 'user_agent' => null])->save();
                    $guests++;
                }
            });

        $this->info("Removed {$events} security event(s) and {$blocks} expired block(s); closed {$guests} idle visitor session(s).");

        return self::SUCCESS;
    }
}
