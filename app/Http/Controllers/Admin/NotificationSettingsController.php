<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Support\Notifications\NotificationCatalog;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ §20 "Notification Templates" and "Notification Settings" (permission
 * notifications.manage, checked on the route): what each role receives by
 * default for each category, and the wording and channels of each
 * notification type.
 */
class NotificationSettingsController extends Controller
{
    public function edit(NotificationPreferences $preferences): Response
    {
        $config = $preferences->config();

        return Inertia::render('admin/notifications/Settings', [
            'categories' => collect(NotificationCatalog::CATEGORIES)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values(),
            'roles' => collect(Role::cases())->map(fn (Role $role) => [
                'key' => $role->value,
                'label' => $role->label(),
                'defaults' => collect(array_keys(NotificationCatalog::CATEGORIES))->mapWithKeys(fn (string $category) => [
                    $category => $preferences->roleDefault([$role->value], $category),
                ]),
            ])->values(),
            'templates' => collect(NotificationCatalog::TYPES)->map(fn (array $type, string $class) => [
                'type' => $class,
                'label' => $type['label'],
                'category' => $type['category'],
                'mandatory' => $type['mandatory'],
                ...$preferences->template($class),
            ])->values(),
            'placeholders' => NotificationCatalog::PLACEHOLDERS,
        ]);
    }

    public function update(Request $request, NotificationPreferences $preferences): RedirectResponse
    {
        $categories = array_keys(NotificationCatalog::CATEGORIES);
        $types = array_keys(NotificationCatalog::TYPES);

        $data = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['array'],
            'roles.*.*.in_app' => ['required', 'boolean'],
            'roles.*.*.email' => ['required', 'boolean'],
            'templates' => ['required', 'array'],
            'templates.*.in_app' => ['required', 'boolean'],
            'templates.*.email' => ['required', 'boolean'],
            'templates.*.email_subject' => ['nullable', 'string', 'max:150'],
            'templates.*.email_body' => ['nullable', 'string', 'max:3000'],
        ]);

        $roles = [];

        foreach ($data['roles'] as $role => $rows) {
            abort_unless(Role::tryFrom($role) !== null, 422);

            foreach ($rows as $category => $row) {
                abort_unless(in_array($category, $categories, true), 422);
                $roles[$role][$category] = ['in_app' => (bool) $row['in_app'], 'email' => (bool) $row['email']];
            }
        }

        $templates = [];

        foreach ($data['templates'] as $type => $row) {
            abort_unless(in_array($type, $types, true), 422);
            $mandatory = NotificationCatalog::TYPES[$type]['mandatory'];
            $templates[$type] = [
                'in_app' => $mandatory ? true : (bool) $row['in_app'],
                'email' => (bool) $row['email'],
                'email_subject' => trim((string) ($row['email_subject'] ?? '')),
                'email_body' => trim((string) ($row['email_body'] ?? '')),
            ];
        }

        $preferences->save(['roles' => $roles, 'templates' => $templates]);
        activity('settings')->causedBy($request->user())->log('Updated notification templates and settings');
        $this->toast(['type' => 'success', 'message' => __('Notification settings saved.')]);

        return back();
    }
}
