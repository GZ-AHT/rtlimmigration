<?php

namespace App\Http\Middleware;

use App\Support\System\IpMatcher;
use App\Support\System\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance mode (REQ §23.5): visitors see a 503 page while it is on, either
 * switched on by hand or inside its scheduled window. Staff who can manage
 * system settings, listed IP addresses, sign-in pages and the callbacks that
 * payment and chat services send still get through.
 */
class EnforceMaintenance
{
    /** Paths that must keep working so staff can sign in and money and messages aren't lost. */
    private const OPEN = ['login', 'logout', 'forgot-password', 'reset-password*', 'two-factor-challenge', 'up', 'payments/gateway/*', 'chat/webhook/*'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::active() || $request->is(...self::OPEN) || $request->user()?->can('settings.manage')
            || IpMatcher::matches($request->ip(), SystemSettings::lines('maintenance.allow_ips'))) {
            return $next($request);
        }

        abort(503, (string) SystemSettings::get('maintenance.message'), ['Retry-After' => '600']);
    }

    public static function active(): bool
    {
        if (SystemSettings::get('maintenance.enabled')) {
            return true;
        }

        $start = SystemSettings::get('maintenance.starts_at');
        $end = SystemSettings::get('maintenance.ends_at');

        return filled($start) && now()->gte($start) && (blank($end) || now()->lt($end));
    }
}
