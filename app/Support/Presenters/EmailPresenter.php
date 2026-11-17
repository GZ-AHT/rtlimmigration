<?php

namespace App\Support\Presenters;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\Student;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/** Shapes email records for the panel (REQ §3, §19). Never includes mailbox passwords. */
class EmailPresenter
{
    /** @return array<string, mixed> */
    public static function account(EmailAccount $account): array
    {
        return [
            'id' => $account->id,
            'email' => $account->email,
            'domain' => $account->domain,
            'display_name' => $account->display_name,
            'quota_mb' => $account->quota_mb,
            'disk_used_mb' => $account->disk_used_mb,
            'usage_percent' => $account->quota_mb && $account->disk_used_mb !== null ? min(100, (int) round($account->disk_used_mb / $account->quota_mb * 100)) : null,
            'status' => $account->status->value,
            'status_label' => $account->status->label(),
            'status_tone' => $account->status->tone(),
            'on_server' => $account->on_server,
            'has_password' => filled($account->getRawOriginal('password')),
            'synced_at' => $account->synced_at?->toIso8601String(),
            'users' => $account->relationLoaded('users') ? $account->users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'can_send' => (bool) $u->getRelationValue('pivot')?->getAttribute('can_send'),
            ])->values()->all() : [],
        ];
    }

    /** @return array<string, mixed> */
    public static function message(EmailMessage $message): array
    {
        return [
            'id' => $message->id,
            'subject' => $message->subject,
            'from_email' => $message->from_email,
            'to_email' => $message->to_email,
            'to_name' => $message->to_name,
            'recipient' => self::recipientLabel($message),
            'sender' => $message->relationLoaded('sender') ? $message->sender?->name : null,
            'campaign' => $message->relationLoaded('campaign') ? $message->campaign?->name : null,
            'status' => $message->status->value,
            'status_label' => $message->status->label(),
            'status_tone' => $message->status->tone(),
            'error' => $message->error,
            'attachments' => array_map(fn (array $a) => $a['name'], $message->attachments ?? []),
            'sent_at' => $message->sent_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
            'opened_at' => $message->opened_at?->toIso8601String(),
            'open_count' => $message->open_count,
            'clicked_at' => $message->clicked_at?->toIso8601String(),
            'click_count' => $message->click_count,
        ];
    }

    /** @return array<string, mixed> */
    public static function activity(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'summary' => (string) $activity->description,
            'email' => $activity->getProperty('email'),
            'causer' => $activity->causer?->getAttribute('name'),
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }

    private static function recipientLabel(EmailMessage $message): ?string
    {
        if (! $message->relationLoaded('recipient') || $message->recipient === null) {
            return null;
        }

        return match (true) {
            $message->recipient instanceof Student => $message->recipient->student_number,
            $message->recipient instanceof Lead => $message->recipient->lead_number,
            default => null,
        };
    }
}
