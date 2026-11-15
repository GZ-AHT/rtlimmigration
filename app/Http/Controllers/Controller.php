<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

abstract class Controller
{
    /**
     * Show a toast on the next page (read by resources/js/lib/flashToast.ts).
     *
     * @param  array{type: 'success'|'info'|'warning'|'error', message: string}  $toast
     */
    protected function toast(array $toast): void
    {
        Inertia::flash('toast', $toast);
    }
}
