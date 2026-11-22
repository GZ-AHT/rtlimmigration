<?php

namespace App\Http\Controllers;

use App\Support\Notifications\NotificationCatalog;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-user notification centre (REQ §1, §20 "Read / Unread Status",
 * "In-app Notifications"). Shared by all four dashboard areas. Users only
 * ever see and change their own notifications.
 */
class NotificationController extends Controller
{
    public function index(Request $request, NotificationPreferences $preferences): Response
    {
        $data = $request->validate([
            'filter' => ['nullable', Rule::in(['all', 'unread'])],
            'category' => ['nullable', Rule::in(array_keys(NotificationCatalog::CATEGORIES))],
        ]);
        $filter = $data['filter'] ?? 'all';
        $category = $data['category'] ?? null;
        $user = $request->user();

        $notifications = $user->notifications()
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($category !== null, fn ($q) => $q->where('data->category', $category))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $n) => self::present($n));

        $unreadByCategory = $user->unreadNotifications()
            ->reorder()
            ->selectRaw("json_unquote(json_extract(data, '$.category')) as category, count(*) as total")
            ->groupBy('category')->pluck('total', 'category');

        return Inertia::render('notifications/Index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'category' => $category,
            'categories' => collect(NotificationCatalog::CATEGORIES)->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'unread' => (int) ($unreadByCategory[$key] ?? 0),
                'in_app' => $preferences->forUser($user, $key)['in_app'],
                'email' => $preferences->forUser($user, $key)['email'],
            ])->values(),
            'customised' => $user->notification_preferences !== null,
        ]);
    }

    /** Mark one as read, then follow its link if it has one. */
    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        // Only follow same-site relative paths (no open redirects).
        $safe = is_string($url) && preg_match('#^/(?![/\\\\])#', $url) === 1;

        return $safe ? redirect($url) : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        $this->toast(['type' => 'success', 'message' => __('All notifications marked as read.')]);

        return back();
    }

    /** @return array<string, mixed> */
    public static function present(DatabaseNotification $n): array
    {
        return [
            'id' => $n->id,
            'title' => (string) ($n->data['title'] ?? __('Notification')),
            'body' => (string) ($n->data['body'] ?? ''),
            'icon' => (string) ($n->data['icon'] ?? 'notifications'),
            'category' => (string) ($n->data['category'] ?? 'system'),
            'has_link' => ! empty($n->data['url']),
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}
