<?php

namespace App\Http\Middleware;

use App\Support\System\IpMatcher;
use App\Support\System\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** IP restrictions (REQ §23.2): a block list for the whole site, and an allow list for the staff areas. */
class EnforceIpRules
{
    /** URL prefixes of the staff areas (the student portal is not one). */
    private const STAFF = ['admin', 'admin/*', 'counselor', 'counselor/*', 'accounts', 'accounts/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        abort_if(IpMatcher::matches($ip, SystemSettings::lines('security.blocked_ips')), 403);

        $allowed = SystemSettings::lines('security.admin_ip_allowlist');

        abort_if($allowed !== [] && $request->is(...self::STAFF) && ! IpMatcher::matches($ip, $allowed), 403);

        return $next($request);
    }
}
