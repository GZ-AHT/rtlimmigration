<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function invitationUrl(User $user, ?string $email = null, int $hours = 72): string
{
    return URL::temporarySignedRoute('invitation.accept', now()->addHours($hours), [
        'user' => $user->id,
        'hash' => sha1($email ?? $user->email),
    ]);
}

it('opens the set-password screen from a valid invitation', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->get(invitationUrl($user));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/reset-password/');
});

it('rejects tampered, expired or outdated invitations', function () {
    $user = User::factory()->create();

    $this->get(invitationUrl($user).'x')->assertForbidden();

    $this->travel(73)->hours();
    $this->get(invitationUrl($user, hours: -1))->assertForbidden();
    $this->travelBack();

    // Sent before an email change: the hash no longer matches.
    $this->get(invitationUrl($user, 'old@example.com'))->assertForbidden();
});

it('sends people who already signed in to the login page', function () {
    $user = User::factory()->create(['last_login_at' => now()]);

    $this->get(invitationUrl($user))->assertRedirect(route('login'));
});

it('verifies the email once the password is set from the emailed link', function () {
    $user = User::factory()->unverified()->create();
    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});
