<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ShareWebsiteData;
use App\Support\Cms\WebsiteMenus;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Payment gateway callbacks and Tawk.to webhooks come from those
        // services, not from our pages.
        $middleware->validateCsrfTokens(except: ['payments/gateway/*', 'chat/webhook/*']);

        $middleware->web(append: [
            EnsureUserIsActive::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Website-styled error pages (REQ §21.8 404 page). Debug mode keeps
        // Laravel's detailed page for server errors.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || ! in_array($status, [403, 404, 419, 429, 500, 503], true) || ($status === 500 && config('app.debug'))) {
                return $response;
            }

            if ($status === 419) {
                Inertia::flash('toast', ['type' => 'error', 'message' => __('The page expired. Please try again.')]);

                return back();
            }

            try {
                $menus = app(WebsiteMenus::class)->for($request->user());
            } catch (Throwable) {
                $menus = [];
            }

            return Inertia::render('website/Error', ['status' => $status, 'menus' => $menus, 'siteMedia' => fn () => ShareWebsiteData::media()])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
