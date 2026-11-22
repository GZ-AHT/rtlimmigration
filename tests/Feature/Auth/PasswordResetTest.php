<?php

namespace Tests\Feature\Auth;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_forgot_password_sends_a_code_not_a_link()
    {
        Notification::fake();
        Mail::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('password.otp'));

        Notification::assertNothingSent();
        Mail::assertQueued(OtpMail::class);
    }

    /** A link (as an admin sends from the Users screen) still opens the reset screen. */
    protected function linkFor(User $user): string
    {
        return Password::broker()->createToken($user);
    }

    public function test_reset_password_screen_can_be_rendered_from_an_admin_link()
    {
        $user = User::factory()->create();

        $this->get(route('password.reset', $this->linkFor($user)))->assertOk();
    }

    public function test_password_can_be_reset_with_valid_admin_link_token()
    {
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => $this->linkFor($user),
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
