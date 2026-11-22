<?php

namespace App\Support\Notifications;

use App\Models\User;
use App\Support\Settings\SettingsRepository;

/**
 * Decides where a notification is delivered (REQ §20 "Notification Settings").
 *
 * For each category an administrator sets a default per role (in-app and
 * email); a person can change their own choice on the notification page.
 * Order of precedence: the person's own choice, then the default for their
 * role, then the built-in default (in-app on, email off). A template can
 * also switch one notification type off for a channel. Mandatory types
 * (security alerts) always reach the in-app centre.
 *
 * @phpstan-type Channels array{in_app: bool, email: bool}
 * @phpstan-type Template array{in_app: bool, email: bool, email_subject: string, email_body: string}
 */
class NotificationPreferences
{
    public const DEFAULTS = ['in_app' => true, 'email' => false];

    public function __construct(private readonly SettingsRepository $settings) {}

    /** @return array{templates: array<string, mixed>, roles: array<string, mixed>} */
    public function config(): array
    {
        $config = $this->settings->get('notifications.config', []);

        return [
            'templates' => is_array($config['templates'] ?? null) ? $config['templates'] : [],
            'roles' => is_array($config['roles'] ?? null) ? $config['roles'] : [],
        ];
    }

    /** @param  array{templates: array<string, mixed>, roles: array<string, mixed>}  $config */
    public function save(array $config): void
    {
        $this->settings->set('notifications.config', $config);
    }

    /** @return Template */
    public function template(string $type): array
    {
        $saved = $this->config()['templates'][$type] ?? [];

        return [
            'in_app' => (bool) ($saved['in_app'] ?? true),
            'email' => (bool) ($saved['email'] ?? true),
            'email_subject' => (string) ($saved['email_subject'] ?? ''),
            'email_body' => (string) ($saved['email_body'] ?? ''),
        ];
    }

    /**
     * What a role gets for a category when the person has made no choice.
     *
     * @param  list<string>  $roles
     * @return Channels
     */
    public function roleDefault(array $roles, string $category): array
    {
        $rows = [];

        foreach ($roles as $role) {
            $row = $this->config()['roles'][$role][$category] ?? null;

            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        if ($rows === []) {
            return self::DEFAULTS;
        }

        // Someone who holds several roles gets the most generous setting.
        return [
            'in_app' => in_array(true, array_map(fn (array $r) => (bool) ($r['in_app'] ?? true), $rows), true),
            'email' => in_array(true, array_map(fn (array $r) => (bool) ($r['email'] ?? false), $rows), true),
        ];
    }

    /** @return Channels what this person receives for a category, before per-type rules */
    public function forUser(User $user, string $category): array
    {
        $own = $user->notification_preferences[$category] ?? null;
        $role = $this->roleDefault(array_values($user->getRoleNames()->map(fn ($name) => (string) $name)->all()), $category);

        return is_array($own)
            ? ['in_app' => (bool) ($own['in_app'] ?? $role['in_app']), 'email' => (bool) ($own['email'] ?? $role['email'])]
            : $role;
    }

    /**
     * Laravel channels for one notification sent to one person.
     *
     * @return list<string>
     */
    public function channels(object $notifiable, string $type, string $category): array
    {
        if (! $notifiable instanceof User) {
            return ['database'];
        }

        $mandatory = NotificationCatalog::TYPES[$type]['mandatory'] ?? false;
        $template = $this->template($type);
        $person = $this->forUser($notifiable, $category);
        $channels = [];

        if ($mandatory || ($person['in_app'] && $template['in_app'])) {
            $channels[] = 'database';
        }

        if ($person['email'] && $template['email'] && $notifiable->email !== '') {
            $channels[] = SafeMailChannel::class;
        }

        return $channels;
    }
}
