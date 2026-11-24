<?php

namespace Tests\Feature\Auth;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\Auth\Otp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** The plain code from the last queued email (the database only holds a hash). */
    private function sentCode(): string
    {
        $code = null;
        Mail::assertQueued(OtpMail::class, function (OtpMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return (string) $code;
    }

    private function wrongCode(string $right): string
    {
        return $right === '000000' ? '111111' : '000000';
    }

    // ---- email verification -------------------------------------------------

    public function test_registration_emails_a_six_digit_code(): void
    {
        Mail::fake();

        $this->post(route('register.store'), [
            'name' => 'Test Student',
            'email' => 'newstudent@rtl-test.org',
            'password' => 'Password-12345',
            'password_confirmation' => 'Password-12345',
        ])->assertRedirect();

        $code = $this->sentCode();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        Mail::assertQueued(OtpMail::class, fn (OtpMail $m) => $m->hasTo('newstudent@rtl-test.org') && $m->purpose === Otp::VERIFY_EMAIL);
    }

    public function test_the_code_is_stored_hashed_with_a_five_minute_life(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $user->sendEmailVerificationNotification();

        $code = $this->sentCode();
        $row = OtpCode::firstOrFail();
        $this->assertNotSame($code, $row->code_hash);
        $this->assertStringNotContainsString($code, json_encode($row->getAttributes()));
        $this->assertEqualsWithDelta(5 * 60, now()->diffInSeconds($row->expires_at, false), 5);
    }

    public function test_correct_code_verifies_the_email_once(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $code = $this->sentCode();

        $this->actingAs($user)->post(route('verification.otp'), ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // The same code cannot be replayed (e.g. after the address is changed back to unverified).
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user->fresh())->post(route('verification.otp'), ['code' => $code])
            ->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_wrong_code_is_rejected_and_five_misses_lock_the_code(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $code = $this->sentCode();
        $bad = $this->wrongCode($code);

        foreach (range(1, 5) as $i) {
            $this->actingAs($user)->post(route('verification.otp'), ['code' => $bad])->assertSessionHasErrors('code');
        }

        // Even the right code no longer works: a fresh one is needed.
        $this->actingAs($user)->post(route('verification.otp'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_code_is_rejected(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $code = $this->sentCode();

        $this->travel(5)->minutes();
        $this->travel(1)->seconds();

        $this->actingAs($user)->post(route('verification.otp'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_code_is_valid_just_before_five_minutes(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $code = $this->sentCode();

        $this->travel(4)->minutes();

        $this->actingAs($user)->post(route('verification.otp'), ['code' => $code])->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_code_must_be_six_digits(): void
    {
        $user = User::factory()->unverified()->create();

        foreach (['12345', '1234567', 'abcdef', ''] as $bad) {
            $this->actingAs($user)->post(route('verification.otp'), ['code' => $bad])->assertSessionHasErrors('code');
        }
    }

    public function test_resend_waits_sixty_seconds(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();

        $this->actingAs($user)->from('/email/verify')->post(route('verification.send'))->assertSessionHasErrors('code');
        Mail::assertQueuedCount(1);

        $this->travel(61)->seconds();

        $this->actingAs($user)->post(route('verification.send'))->assertSessionHasNoErrors();
        Mail::assertQueuedCount(2);
    }

    public function test_a_resent_code_replaces_the_old_one(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $first = $this->sentCode();

        $this->travel(61)->seconds();
        $this->actingAs($user)->post(route('verification.send'));

        $this->assertSame(1, OtpCode::count());

        $codes = [];
        Mail::assertQueued(OtpMail::class, function (OtpMail $m) use (&$codes) {
            $codes[] = $m->code;

            return true;
        });

        $second = end($codes);

        if ($second !== $first) {
            $this->actingAs($user)->post(route('verification.otp'), ['code' => $first])->assertSessionHasErrors('code');
        }

        $this->actingAs($user)->post(route('verification.otp'), ['code' => $second])->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_screen_gets_the_resend_countdown(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();

        $this->actingAs($user)->get(route('verification.notice'))
            ->assertInertia(fn ($page) => $page->component('auth/VerifyEmail')
                ->where('email', $user->email)
                ->where('resendIn', fn ($v) => $v > 50 && $v <= 60));
    }

    public function test_verification_code_cannot_be_used_to_reset_a_password(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $code = $this->sentCode();

        $this->post(route('password.email'), ['email' => $user->email]);
        $this->post(route('password.otp.verify'), ['code' => $code])->assertSessionHasErrors('code');
    }

    // ---- forgot password ----------------------------------------------------

    private function startReset(User $user): string
    {
        Mail::fake();
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('password.otp'));

        return $this->sentCode();
    }

    public function test_full_password_reset_with_a_code(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->startReset($user);

        $this->get(route('password.otp'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/VerifyResetCode')->where('email', $user->email));

        // No new-password screen until the code is right.
        $this->get(route('password.otp.reset'))->assertRedirect(route('password.request'));

        $this->post(route('password.otp.verify'), ['code' => $code])->assertRedirect(route('password.otp.reset'));
        $this->get(route('password.otp.reset'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/ResetPassword')->where('mode', 'otp'));

        $this->post(route('password.otp.update'), [
            'password' => 'A-brand-new-pass-99',
            'password_confirmation' => 'A-brand-new-pass-99',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('A-brand-new-pass-99', $user->password));
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_reset_is_refused_without_a_verified_code(): void
    {
        $user = User::factory()->create();
        $old = $user->password;
        $this->startReset($user);

        $this->post(route('password.otp.update'), [
            'password' => 'A-brand-new-pass-99',
            'password_confirmation' => 'A-brand-new-pass-99',
        ])->assertRedirect(route('password.request'));

        $this->assertSame($old, $user->fresh()->password);
    }

    public function test_the_reset_step_works_only_once(): void
    {
        $user = User::factory()->create();
        $code = $this->startReset($user);
        $this->post(route('password.otp.verify'), ['code' => $code]);

        $payload = ['password' => 'A-brand-new-pass-99', 'password_confirmation' => 'A-brand-new-pass-99'];
        $this->post(route('password.otp.update'), $payload)->assertRedirect(route('login'));

        $this->post(route('password.otp.update'), ['password' => 'Another-pass-12345', 'password_confirmation' => 'Another-pass-12345'])
            ->assertRedirect(route('password.request'));
        $this->assertTrue(Hash::check('A-brand-new-pass-99', $user->fresh()->password));
    }

    public function test_a_weak_password_does_not_waste_the_verified_code(): void
    {
        $user = User::factory()->create();
        $code = $this->startReset($user);
        $this->post(route('password.otp.verify'), ['code' => $code]);

        $this->post(route('password.otp.update'), ['password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');

        $this->post(route('password.otp.update'), ['password' => 'A-brand-new-pass-99', 'password_confirmation' => 'A-brand-new-pass-99'])
            ->assertRedirect(route('login'));
    }

    public function test_the_reset_grant_expires(): void
    {
        $user = User::factory()->create();
        $code = $this->startReset($user);
        $this->post(route('password.otp.verify'), ['code' => $code]);

        $this->travel(11)->minutes();

        $this->get(route('password.otp.reset'))->assertRedirect(route('password.request'));
    }

    public function test_wrong_reset_codes_are_limited(): void
    {
        $user = User::factory()->create();
        $code = $this->startReset($user);
        $bad = $this->wrongCode($code);

        foreach (range(1, 5) as $i) {
            $this->post(route('password.otp.verify'), ['code' => $bad])->assertSessionHasErrors('code');
        }

        $this->post(route('password.otp.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->get(route('password.otp.reset'))->assertRedirect(route('password.request'));
    }

    public function test_unknown_and_known_emails_get_the_same_answers(): void
    {
        // The resend countdown is read from the clock, so freeze it or a second can tick between the two requests.
        $this->freezeTime();

        $known = User::factory()->create();
        Mail::fake();

        $this->post(route('password.email'), ['email' => $known->email])->assertRedirect(route('password.otp'));
        $knownScreen = $this->get(route('password.otp'))->viewData('page')['props'];

        $this->flushSession();
        Cache::flush();

        $this->post(route('password.email'), ['email' => 'nobody@rtl-test.org'])->assertRedirect(route('password.otp'));
        $unknownScreen = $this->get(route('password.otp'))->viewData('page')['props'];

        $this->assertSame(array_keys($knownScreen), array_keys($unknownScreen));
        $this->assertSame($knownScreen['resendIn'], $unknownScreen['resendIn']);
        Mail::assertQueuedCount(1);
        Mail::assertNotQueued(OtpMail::class, fn (OtpMail $m) => $m->hasTo('nobody@rtl-test.org'));

        // Wrong guesses and the lock-out read the same for a real and an unknown address.
        $lockMessage = function (string $email) {
            $this->flushSession();
            Cache::flush();
            $this->post(route('password.email'), ['email' => $email]);
            $last = null;

            foreach (range(1, 6) as $i) {
                $last = $this->post(route('password.otp.verify'), ['code' => '123456']);
            }

            $errors = $last->getSession()->get('errors');

            return is_array($errors) ? ($errors['code'][0] ?? $errors['code'] ?? null) : $errors->getBag('default')->first('code');
        };

        $this->assertSame($lockMessage('nobody@rtl-test.org'), $lockMessage($known->email));
    }

    public function test_reset_resend_waits_sixty_seconds_for_everyone(): void
    {
        $user = User::factory()->create();
        Mail::fake();
        $this->post(route('password.email'), ['email' => $user->email]);

        $this->post(route('password.otp.resend'))->assertSessionHasErrors('code');
        Mail::assertQueuedCount(1);

        $this->travel(61)->seconds();
        $this->post(route('password.otp.resend'))->assertSessionHasNoErrors();
        Mail::assertQueuedCount(2);
    }

    public function test_deactivated_accounts_get_no_code(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);
        Mail::fake();

        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('password.otp'));

        Mail::assertNothingQueued();
    }

    public function test_forgot_password_requires_a_valid_email_format(): void
    {
        $this->post(route('password.email'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    }

    public function test_email_template_is_branded_html_with_the_code(): void
    {
        $html = (new OtpMail('Ayesha', '482913', Otp::PASSWORD_RESET))->render();

        $this->assertStringContainsString('482913', $html);
        $this->assertStringContainsString('5 minutes', $html);
        $this->assertStringContainsString('Never share this code', $html);
        $this->assertStringNotContainsString('http', preg_replace('/xmlns="[^"]*"/', '', $html));
    }

    public function test_codes_are_random_not_fixed(): void
    {
        Mail::fake();
        $seen = [];

        foreach (range(1, 8) as $i) {
            $user = User::factory()->unverified()->create();
            $user->sendEmailVerificationNotification();
        }

        Mail::assertQueued(OtpMail::class, function (OtpMail $m) use (&$seen) {
            $seen[$m->code] = true;

            return true;
        });

        $this->assertGreaterThan(3, count($seen));
    }
}
