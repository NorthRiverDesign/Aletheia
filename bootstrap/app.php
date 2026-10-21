<?php

use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            'throttle:web',
            EnsureUserIsNotBanned::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A throttled form submission goes back to the form with a toast instead
        // of an error page. Throttled page visits keep the plain 429 response,
        // since sending them back would just hit the limit again.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->header('X-Inertia') || $request->isMethodSafe()) {
                return null;
            }

            $message = __('Too many requests. Try again in :seconds seconds.', [
                'seconds' => $e->getHeaders()['Retry-After'] ?? 60,
            ]);

            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return back(303)->withErrors(['throttle' => $message]);
        });
    })->create();
