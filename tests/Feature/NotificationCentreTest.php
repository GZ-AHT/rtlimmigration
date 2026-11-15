<?php

use App\Enums\Role;
use App\Models\User;
use App\Notifications\StudentRegistered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function notifyWithUrl(User $user, ?string $url): string
{
    $id = (string) Str::uuid();
    $user->notifications()->create([
        'id' => $id,
        'type' => 'test',
        'data' => ['title' => 'Hello', 'body' => 'World', 'url' => $url, 'icon' => 'info', 'category' => 'system'],
    ]);

    return $id;
}

it('tells Super Admins about new self-registrations', function () {
    Notification::fake();
    $admin = $this->userWithRole(Role::SuperAdmin);

    $this->post(route('register.store'), [
        'name' => 'New Client', 'email' => 'client@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ]);

    Notification::assertSentTo($admin, StudentRegistered::class);
});

it('lists only your own notifications and filters unread', function () {
    $me = $this->userWithRole(Role::Student);
    $other = $this->userWithRole(Role::Student);
    notifyWithUrl($me, null);
    $readId = notifyWithUrl($me, null);
    $me->notifications()->find($readId)->markAsRead();
    notifyWithUrl($other, null);

    $this->actingAs($me)->get(route('notifications.index'))
        ->assertInertia(fn ($page) => $page->component('notifications/Index')->where('notifications.total', 2));

    $this->actingAs($me)->get(route('notifications.index', ['filter' => 'unread']))
        ->assertInertia(fn ($page) => $page->where('notifications.total', 1));
});

it('marks a notification read and follows its link', function () {
    $me = $this->userWithRole(Role::Student);
    $id = notifyWithUrl($me, '/portal');

    $this->actingAs($me)->post(route('notifications.read', $id))->assertRedirect('/portal');

    expect($me->notifications()->find($id)->read_at)->not->toBeNull();
});

it('refuses links that would leave the site', function (string $url) {
    $me = $this->userWithRole(Role::Student);
    $id = notifyWithUrl($me, $url);

    $response = $this->actingAs($me)->from('/portal')->post(route('notifications.read', $id));

    $response->assertRedirect('/portal');
})->with(['//evil.example', '/\\evil.example', 'https://evil.example']);

it("cannot touch someone else's notification", function () {
    $other = $this->userWithRole(Role::Student);
    $id = notifyWithUrl($other, null);

    $this->actingAs($this->userWithRole(Role::Student))
        ->post(route('notifications.read', $id))
        ->assertNotFound();
});

it('marks everything read and shares unread counts with every page', function () {
    $me = $this->userWithRole(Role::Student);
    notifyWithUrl($me, null);
    notifyWithUrl($me, null);

    $this->actingAs($me)->get(route('portal.dashboard'))
        ->assertInertia(fn ($page) => $page->where('notifications.unread', 2)->has('notifications.recent', 2));

    $this->actingAs($me)->post(route('notifications.read-all'))->assertRedirect();

    expect($me->unreadNotifications()->count())->toBe(0);
});
