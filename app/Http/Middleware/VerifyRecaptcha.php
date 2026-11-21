<?php

namespace App\Http\Middleware;

use App\Support\System\Recaptcha;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Puts reCAPTCHA in front of a public form when it is switched on (System → Settings → Integrations). */
class VerifyRecaptcha
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Recaptcha::enabled() || Recaptcha::passes($request->input('recaptcha_token'), $request->ip())) {
            return $next($request);
        }

        $message = __('We couldn’t confirm you’re not a robot. Please try again, or contact us by phone.');
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        throw ValidationException::withMessages(['recaptcha' => $message]);
    }
}
