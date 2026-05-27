<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust reverse-proxy headers (X-Forwarded-Proto, etc.) so assets/URLs
        // keep HTTPS scheme when app is opened through a public tunnel.
        $middleware->trustProxies(at: '*');

        // API routes must return 401 for guests instead of redirecting to a
        // non-existent "login" route (this app uses token auth for API).
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return '/admin/login';
        });

        // API authentication in this MVP is token-based (Sanctum personal access tokens),
        // so we intentionally keep API stateless and avoid session-cookie precedence.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
