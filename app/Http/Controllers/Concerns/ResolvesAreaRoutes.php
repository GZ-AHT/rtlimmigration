<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * For controllers mounted in more than one dashboard area (e.g. leads under
 * /admin and /counselor): builds links that stay inside the current area.
 */
trait ResolvesAreaRoutes
{
    protected function areaPrefix(Request $request): string
    {
        $prefix = strstr((string) $request->route()?->getName(), '.', true);

        return $prefix !== false && $prefix !== '' ? $prefix : 'admin';
    }

    /** @param  mixed  $parameters */
    protected function areaRoute(Request $request, string $name, $parameters = []): string
    {
        return route($this->areaPrefix($request).'.'.$name, $parameters, absolute: false);
    }

    protected function areaHas(Request $request, string $name): bool
    {
        return Route::has($this->areaPrefix($request).'.'.$name);
    }
}
