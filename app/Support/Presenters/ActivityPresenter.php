<?php

namespace App\Support\Presenters;

use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/** Turns audit-log entries into one-line, human-readable summaries. */
class ActivityPresenter
{
    /** @return array{id: int, log: string|null, event: string|null, summary: string, subject: string|null, causer: string|null, created_at: string|null} */
    public static function present(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'log' => $activity->log_name,
            'event' => $activity->event,
            'summary' => self::summary($activity),
            'subject' => self::subjectName($activity),
            'causer' => $activity->causer?->getAttribute('name'),
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }

    private static function summary(Activity $activity): string
    {
        $changes = $activity->attribute_changes;
        $fields = $changes ? array_keys((array) ($changes['attributes'] ?? [])) : [];
        $labels = [
            'email_verified_at' => __('email verification'), 'status' => __('status'), 'name' => __('name'), 'email' => __('email'),
            'counselor_id' => __('counselor'), 'country_id' => __('destination'), 'service_id' => __('service'),
            'next_follow_up_at' => __('next follow-up'), 'assigned_to' => __('assignee'), 'category_id' => __('category'),
            'priority' => __('priority'), 'availability' => __('availability'), 'max_active_leads' => __('lead limit'),
        ];
        $fieldList = implode(', ', array_map(fn ($f) => $labels[$f] ?? str_replace('_', ' ', (string) $f), $fields));

        $noun = match ($activity->log_name) {
            'users' => __('Account'),
            'leads' => __('Lead'),
            'tickets' => __('Ticket'),
            'counselors' => __('Counselor profile'),
            'students' => __('Student'),
            default => __('Record'),
        };

        return match ($activity->event) {
            'created' => __(':noun created', ['noun' => $noun]),
            'updated' => $fieldList !== '' ? __('Updated :fields', ['fields' => $fieldList]) : __('Updated details'),
            'deleted' => __(':noun deleted', ['noun' => $noun]),
            'restored' => __(':noun restored', ['noun' => $noun]),
            'counselor_assigned' => __('Counselor set to :name', ['name' => $activity->getProperty('counselor') ?? __('none')]),
            'roles_updated' => __('Roles changed from :old to :new', [
                'old' => self::roleList($activity->getProperty('old', [])),
                'new' => self::roleList($activity->getProperty('new', [])),
            ]),
            'role_created' => __('Role created with :count permissions', ['count' => count((array) $activity->getProperty('added', []))]),
            'role_updated' => __('Role permissions changed (+:added / -:removed)', [
                'added' => count((array) $activity->getProperty('added', [])),
                'removed' => count((array) $activity->getProperty('removed', [])),
            ]),
            'role_deleted' => __('Role deleted'),
            default => Str::headline((string) $activity->description),
        };
    }

    private static function subjectName(Activity $activity): ?string
    {
        if ($activity->log_name === 'roles') {
            return UserPresenter::roleLabel((string) $activity->getProperty('role', ''));
        }

        $name = $activity->subject?->getAttribute('name');

        return is_string($name) ? $name : null;
    }

    private static function roleList(mixed $roles): string
    {
        $roles = is_array($roles) ? $roles : [];

        return $roles === [] ? __('none') : implode(', ', array_map(fn ($r) => UserPresenter::roleLabel((string) $r), $roles));
    }
}
