<?php

namespace App\Http\Middleware;

use App\Enums\Area;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts public website page views for the traffic report (REQ §24).
 *
 * Only successful page loads are counted. Staff, bots and prefetch requests
 * are skipped. The visitor is a hash of IP, browser and the date, so it
 * changes every day and no IP address or cookie is stored. Recording runs
 * after the response is sent and can never break a page.
 */
class TrackPageView
{
    private const BOT = '/bot|crawl|spider|slurp|bing|facebookexternalhit|preview|monitor|uptime|curl|wget|python|headless|lighthouse/i';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200 || $this->skip($request)) {
            return;
        }

        $path = '/'.ltrim($request->path(), '/');
        $day = now()->toDateString();
        $visitor = sha1(implode('|', [$request->ip(), $request->userAgent(), $day, config('app.key')]));

        try {
            DB::table('page_views')->upsert(
                [['viewed_on' => $day, 'path' => mb_substr($path, 0, 191), 'visitor_hash' => $visitor, 'hits' => 1]],
                ['viewed_on', 'path', 'visitor_hash'],
                ['hits' => DB::raw('hits + 1')],
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function skip(Request $request): bool
    {
        if ($request->is('sitemap.xml', 'sitemap-*.xml', 'robots.txt')) {
            return true;
        }

        $agent = (string) $request->userAgent();

        if ($agent === '' || preg_match(self::BOT, $agent) === 1 || $request->headers->get('Purpose') === 'prefetch' || $request->headers->get('Sec-Purpose') !== null) {
            return true;
        }

        $user = $request->user();

        return $user !== null && $user->homeArea() !== Area::Portal;
    }
}
