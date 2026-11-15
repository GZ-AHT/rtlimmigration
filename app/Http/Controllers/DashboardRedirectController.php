<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Fortify sends every user to /dashboard after login or registration. This
 * forwards them to the dashboard for their role (REQ §30: three dashboards
 * plus the admin panel, one login).
 */
class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $area = $request->user()->homeArea();

        abort_if($area === null, 403, 'Your account has no dashboard assigned yet. Please contact an administrator.');

        return redirect()->route($area->dashboardRoute());
    }
}
