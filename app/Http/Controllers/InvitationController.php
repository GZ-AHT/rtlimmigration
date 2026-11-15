<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

/**
 * Opens a signed invitation link (REQ §11.2) and hands the user to the
 * standard password reset screen with a fresh token, so the password is
 * chosen by the user and never travels by email.
 */
class InvitationController extends Controller
{
    public function __invoke(Request $request, User $user, string $hash): RedirectResponse
    {
        // The hash ties the link to the email it was sent to, so changing a
        // user's email invalidates earlier invitations.
        abort_unless(hash_equals(sha1($user->email), $hash), 403);

        if ($user->last_login_at !== null) {
            $this->toast(['type' => 'info', 'message' => __('This invitation has already been used. Please sign in.')]);

            return to_route('login');
        }

        Auth::guard('web')->logout();

        return to_route('password.reset', [
            'token' => Password::broker()->createToken($user),
            'email' => $user->email,
        ]);
    }
}
