<?php

namespace App\Support\Seo;

use App\Enums\Area;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Remembers public addresses that answered 404 (REQ §22.3 "404 log"), so an
 * administrator can send them somewhere useful. Only visitors' GET requests
 * count: staff pages, scanners probing for admin files, and uploads are left
 * out, and the table stops taking new addresses at MAX_PATHS so a crawler
 * cannot fill it. Recording can never break the 404 page itself.
 */
class NotFoundRecorder
{
    public const MAX_PATHS = 2000;

    /** Area prefixes and probe patterns that say nothing about the public website. */
    private const SKIP = '~^/(admin|counselor|accounts|portal|api|storage|build|livewire|wp-|\.|cgi-bin|vendor|node_modules)|\.(php\d?|asp|aspx|jsp|env|git|sql|bak|zip|map)$~i';

    public function record(Request $request): void
    {
        if (! $request->isMethod('GET')) {
            return;
        }

        $path = Redirect::normalize($request->getPathInfo());

        if ($path === '' || strlen($path) > 191 || preg_match(self::SKIP, $path) === 1) {
            return;
        }

        $user = $request->user();

        // Staff mistyping an address is not a broken public link.
        if ($user !== null && $user->homeArea() !== Area::Portal) {
            return;
        }

        try {
            $referrer = $this->externalReferrer($request);

            $updated = NotFoundLog::query()->where('path', $path)->update([
                'hits' => DB::raw('hits + 1'),
                'last_seen_at' => now(),
                ...($referrer !== null ? ['referrer' => $referrer] : []),
            ]);

            if ($updated === 0 && NotFoundLog::query()->count() < self::MAX_PATHS) {
                NotFoundLog::query()->insertOrIgnore([
                    'path' => $path, 'hits' => 1, 'referrer' => $referrer, 'first_seen_at' => now(), 'last_seen_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Where the visitor came from, if that was another website (links from our own pages are not interesting). */
    private function externalReferrer(Request $request): ?string
    {
        $referrer = (string) $request->headers->get('referer', '');
        $host = parse_url($referrer, PHP_URL_HOST);

        if ($referrer === '' || ! is_string($host) || strcasecmp($host, $request->getHost()) === 0) {
            return null;
        }

        return mb_substr($referrer, 0, 255);
    }
}
