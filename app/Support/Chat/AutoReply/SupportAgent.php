<?php

namespace App\Support\Chat\AutoReply;

use App\Models\AuthorProfile;
use App\Models\Media;
use App\Models\User;

/**
 * Who a visitor is talking to once a chat is handed to a person: the name
 * and profile picture shown beside their messages. The picture comes from the
 * handover settings (for the configured agent), else the person's author
 * profile (Blog → Authors); with neither, the page shows a default avatar.
 */
class SupportAgent
{
    /** @var array<int|string, string|null> */
    private static array $avatars = [];

    public static function flush(): void
    {
        self::$avatars = [];
    }

    public static function name(?User $user): string
    {
        $settings = app(AutoReplySettings::class);

        // The configured agent keeps the display name set for the handover (e.g. "MD Hasnat").
        return $user === null || $user->id === $settings->handoverAgentId()
            ? $settings->handoverAgentName()
            : $user->name;
    }

    public static function avatarUrl(?User $user): ?string
    {
        $key = $user === null ? 'none' : $user->id;

        if (array_key_exists($key, self::$avatars)) {
            return self::$avatars[$key];
        }

        $settings = app(AutoReplySettings::class);
        $configured = $user === null || $user->id === $settings->handoverAgentId();
        $mediaId = $configured ? $settings->handoverAvatarId() : null;
        $url = $mediaId ? Media::query()->find($mediaId)?->url() : null;

        if ($url === null && $user !== null) {
            $url = AuthorProfile::query()->where('user_id', $user->id)->first()?->avatar?->url();
        }

        return self::$avatars[$key] = $url;
    }

    /** The configured agent, if still an active user. */
    public static function configured(): ?User
    {
        $id = app(AutoReplySettings::class)->handoverAgentId();

        return $id ? User::query()->find($id) : null;
    }
}
