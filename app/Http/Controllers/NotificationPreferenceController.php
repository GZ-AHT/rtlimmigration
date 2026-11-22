<?php

namespace App\Http\Controllers;

use App\Support\Notifications\NotificationCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** A person's own notification choices per category (REQ §20 "Notification Settings"). */
class NotificationPreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reset' => ['sometimes', 'boolean'],
            'preferences' => ['required_without:reset', 'array'],
            'preferences.*.in_app' => ['required', 'boolean'],
            'preferences.*.email' => ['required', 'boolean'],
        ]);

        $preferences = [];

        foreach ($data['preferences'] ?? [] as $category => $row) {
            abort_unless(array_key_exists($category, NotificationCatalog::CATEGORIES), 422);
            $preferences[$category] = ['in_app' => (bool) $row['in_app'], 'email' => (bool) $row['email']];
        }

        $request->user()->forceFill(['notification_preferences' => ($data['reset'] ?? false) ? null : $preferences])->save();

        $this->toast(['type' => 'success', 'message' => ($data['reset'] ?? false) ? __('Notification choices reset to your role defaults.') : __('Notification choices saved.')]);

        return back();
    }
}
