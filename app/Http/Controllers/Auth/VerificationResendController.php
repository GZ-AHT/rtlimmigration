<?php

namespace App\Http\Controllers\Auth;

use App\Support\Auth\Otp;
use Illuminate\Http\Request;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;

/**
 * Fortify's "resend verification" route (verification.send), now sending a
 * code and holding to the 60-second wait. Bound over Fortify's controller
 * in FortifyServiceProvider so the route name and URL stay as they were.
 */
class VerificationResendController extends EmailVerificationNotificationController
{
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return parent::store($request);
        }

        $wait = app(Otp::class)->send($user->email, Otp::VERIFY_EMAIL, $user);

        if ($wait > 0) {
            return back()->withErrors(['code' => __('Please wait :seconds seconds before asking for another code.', ['seconds' => $wait])]);
        }

        return back()->with('status', 'otp-sent');
    }
}
