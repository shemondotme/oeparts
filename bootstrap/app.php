<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'set.locale'     => \App\Http\Middleware\SetLocale::class,
            'admin.auth'     => \App\Http\Middleware\AdminAuthenticated::class,
            'normalize.oem'  => \App\Http\Middleware\NormalizeOemUrl::class,
            'maintenance'    => \App\Http\Middleware\MaintenanceMode::class,
            'ip.blocklist'   => \App\Http\Middleware\IpBlocklist::class,
            'installer'      => \App\Http\Middleware\InstallerMiddleware::class,
            'track.utm'      => \App\Http\Middleware\TrackUtm::class,
            'handle.redirects' => \App\Http\Middleware\HandleRedirects::class,
        ]);
    })
    ->withProviders([
        \App\Providers\EventServiceProvider::class,
    ])
    ->withEvents(false)
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (TooManyRequestsHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('search.error_429_message'),
                ], 429, $e->getHeaders());
            }

            return response()->view('errors.429', [
                'message' => $e->getMessage() ?: __('search.error_429_message'),
            ], 429, $e->getHeaders());
        });
    })->create();
