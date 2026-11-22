<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Support\Auth\Otp;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest;

/**
 * Forgot password with a six-digit code instead of an emailed link:
 * 1. enter email (password.email)  2. enter code  3. choose a new password.
 * Bound over Fortify's PasswordResetLinkController, so password.request and
 * password.email keep their names and URLs.
 *
 * Every answer is the same whether or not the address has an account.
 */
class PasswordOtpController extends PasswordResetLinkController
{
    private const EMAIL = 'password_otp.email';

    private const GRANT = 'password_otp.grant';

    public function __construct()
    {
        $this->middleware('throttle:otp')->only('store');
    }

    /** Step 1: always moves on to the code screen. */
    public function store(SendPasswordResetLinkRequest $request): RedirectResponse
    {
        $email = Str::lower((string) $request->input('email'));

        $user = User::where('email', $email)->first();

        app(Otp::class)->send($email, Otp::PASSWORD_RESET, $user?->isActive() ? $user : null);

        $request->session()->put(self::EMAIL, $email);
        $request->session()->forget(self::GRANT);

        return to_route('password.otp');
    }

    /** Step 2: the code screen. */
    public function code(Request $request, Otp $otp): Response|RedirectResponse
    {
        $email = $request->session()->get(self::EMAIL);

        if (! $email) {
            return to_route('password.request');
        }

        return Inertia::render('auth/VerifyResetCode', [
            'email' => $email,
            'resendIn' => $otp->cooldownRemaining($email, Otp::PASSWORD_RESET),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function verify(Request $request, Otp $otp): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $email = $request->session()->get(self::EMAIL);

        if (! $email) {
            return to_route('password.request');
        }

        $result = $otp->verify($email, Otp::PASSWORD_RESET, (string) $request->input('code'));

        if ($result !== Otp::RESULT_OK) {
            return back()->withErrors(['code' => Otp::message($result)]);
        }

        $request->session()->put(self::GRANT, $otp->grant($email));

        return to_route('password.otp.reset');
    }

    public function resend(Request $request, Otp $otp): RedirectResponse
    {
        $email = $request->session()->get(self::EMAIL);

        if (! $email) {
            return to_route('password.request');
        }

        $user = User::where('email', $email)->first();
        $wait = $otp->send($email, Otp::PASSWORD_RESET, $user?->isActive() ? $user : null);

        if ($wait > 0) {
            return back()->withErrors(['code' => __('Please wait :seconds seconds before asking for another code.', ['seconds' => $wait])]);
        }

        return back()->with('status', 'otp-sent');
    }

    /** Step 3: choose a password. Only reachable after a correct code. */
    public function reset(Request $request, Otp $otp): Response|RedirectResponse
    {
        $email = $request->session()->get(self::EMAIL);
        $grant = $request->session()->get(self::GRANT);

        if (! $email || ! $grant || ! $otp->grantValid($email, $grant)) {
            return to_route('password.request');
        }

        return Inertia::render('auth/ResetPassword', [
            'mode' => 'otp',
            'email' => $email,
            'token' => '',
            'passwordRules' => PasswordRule::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request, Otp $otp, ResetUserPassword $resetter): RedirectResponse
    {
        $email = $request->session()->get(self::EMAIL);
        $grant = $request->session()->get(self::GRANT);

        $user = $email ? User::where('email', $email)->first() : null;

        if (! $email || ! $grant || ! $user || ! $user->isActive()) {
            return to_route('password.request');
        }

        try {
            // The grant is used up in the same transaction as the new
            // password, so a rejected password does not waste it.
            DB::transaction(function () use ($otp, $email, $grant, $resetter, $user, $request) {
                if (! $otp->redeemGrant($email, $grant)) {
                    throw new ExpiredGrant;
                }

                $resetter->reset($user, $request->only('password', 'password_confirmation'));
            });
        } catch (ExpiredGrant) {
            $request->session()->forget([self::EMAIL, self::GRANT]);

            throw ValidationException::withMessages(['password' => __('This reset has expired. Please start again.')])->redirectTo(route('password.request'));
        }

        Password::deleteToken($user);
        event(new PasswordReset($user));
        $otp->forget($email, Otp::PASSWORD_RESET);
        $request->session()->forget([self::EMAIL, self::GRANT]);

        return to_route('login')->with('status', __('Your password has been reset. You can now sign in.'));
    }
}
