<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Sends visitors from an old path to its new address (REQ §22.3, 301 / 302). */
class ApplyRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $hit = Redirect::map()[Redirect::normalize($request->getPathInfo())] ?? null;

        if ($hit === null) {
            return $next($request);
        }

        Redirect::query()->whereKey($hit['id'])->update(['hits' => DB::raw('hits + 1'), 'last_hit_at' => now()]);

        $to = $hit['to'];
        $query = $request->getQueryString();

        if ($query && ! str_contains($to, '?')) {
            $to .= '?'.$query;
        }

        return redirect()->to($to, $hit['status']);
    }
}
