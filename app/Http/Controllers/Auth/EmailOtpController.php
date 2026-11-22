<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auth\Otp;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Confirms the signed-in user's email address with the code we emailed them. */
class EmailOtpController extends Controller
{
    public function verify(Request $request, Otp $otp): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        $result = $otp->verify($user->email, Otp::VERIFY_EMAIL, (string) $request->input('code'));

        if ($result !== Otp::RESULT_OK) {
            return back()->withErrors(['code' => Otp::message($result)]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
