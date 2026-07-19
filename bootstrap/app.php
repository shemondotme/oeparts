<?php

use App\Http\Middleware\EnforceCustomerSessionLifetime;
use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\InstallerMiddleware;
use App\Http\Middleware\IpBlocklist;
use App\Http\Middleware\MaintenanceMode;
use App\Http\Middleware\NormalizeOemUrl;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackUtm;
use App\Models\NotFoundLog;
use App\Providers\EventServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Disable CSRF verification in the test environment so HTTP test
        // client requests don't require a real session token. Real forms
        // always include @csrf; this only affects the PHPUnit HTTP client.
        // $_SERVER['APP_ENV'] is set by phpunit.xml <server> before bootstrap runs.
        if (($_SERVER['APP_ENV'] ?? null) === 'testing') {
            $middleware->validateCsrfTokens(except: ['*']);
        }

        $middleware->alias([
            'set.locale' => SetLocale::class,
            'customer.idle-timeout' => EnforceCustomerSessionLifetime::class,
            'normalize.oem' => NormalizeOemUrl::class,
            'maintenance' => MaintenanceMode::class,
            'ip.blocklist' => IpBlocklist::class,
            'installer' => InstallerMiddleware::class,
            'track.utm' => TrackUtm::class,
            'handle.redirects' => HandleRedirects::class,
            'auth.admin' => \App\Http\Middleware\AuthenticateAdmin::class,
            'csp' => \App\Http\Middleware\ContentSecurityPolicy::class,
            'honeypot' => \Spatie\Honeypot\ProtectAgainstSpam::class,
            'auth.sanctum' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\ContentSecurityPolicy::class,
        ]);
    })
    ->withProviders([
        EventServiceProvider::class,
    ])
    ->withEvents(false)
    ->withExceptions(function (Exceptions $exceptions) {
        // SEO 404 monitor (Module 9) — record every genuine frontend 404 so gaps
        // (dead inbound links, stale sitemaps) show up in the admin instead of
        // only in raw web-server logs. Returning null lets Laravel's default
        // 404 rendering proceed unchanged; this is a side effect only.
        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('admin/*') && ! $request->is('api/*') && ! $request->is('livewire/*')) {
                try {
                    $segments = $request->segments();
                    $lang = (in_array($segments[0] ?? null, ['en', 'de', 'lt', 'fr', 'es'], true))
                        ? $segments[0] : null;

                    NotFoundLog::recordHit(
                        $request->path(),
                        $lang,
                        $request->headers->get('referer'),
                        $request->ip()
                    );
                } catch (\Throwable) {
                    // Logging must never break the 404 response itself.
                }
            }
        });

        $exceptions->renderable(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage() ?: __('search.error_429_message'),
                ], 429, $e->getHeaders());
            }

            return response()->view('errors.429', [
                'message' => $e->getMessage() ?: __('search.error_429_message'),
            ], 429, $e->getHeaders());
        });

    })->create();
