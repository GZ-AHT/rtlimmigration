<?php

use App\Enums\Role;
use App\Models\LoginHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records successful sign-ins and the last login time', function () {
    $user = $this->userWithRole(Role::Student);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $entry = LoginHistory::where('user_id', $user->id)->sole();

    expect($entry->event)->toBe('login')
        ->and($entry->ip_address)->toBe('127.0.0.1')
        ->and($user->refresh()->last_login_at)->not->toBeNull();
});

it('records failed attempts without storing the password', function () {
    $user = $this->userWithRole(Role::Student);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
    $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'whatever']);

    $failed = LoginHistory::where('event', 'failed')->orderBy('id')->get();

    expect($failed)->toHaveCount(2)
        ->and($failed[0]->user_id)->toBe($user->id)
        ->and($failed[1]->user_id)->toBeNull()
        ->and($failed[1]->email)->toBe('nobody@example.com')
        ->and(LoginHistory::where('email', 'like', '%wrong-password%')->exists())->toBeFalse();
});

it('records sign-outs', function () {
    $user = $this->userWithRole(Role::Student);

    $this->actingAs($user)->post(route('logout'));

    expect(LoginHistory::where('user_id', $user->id)->where('event', 'logout')->exists())->toBeTrue();
});
