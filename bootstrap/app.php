<?php

use App\Providers\BroadcastServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Define middleware groups
        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\UpgradeToHttpsUnderNgrok::class,  // Keep if you have it
        ]);

        $middleware->group('api', [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,  // Enables auth:sanctum for SPA
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',  // Enables throttle:api (uses your 'api' limiter)
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // ForceJsonResponse: Alias it below and add here if it's custom
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        // Define aliases (fixed 'signed' to ValidateSignedRoute; added 'force.json' if needed)
        $middleware->alias([
            'auth' => \Illuminate\Auth\Middleware\Authenticate::class,  // Use Laravel's default (not custom if possible)
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'auth.admin' => \App\Http\Middleware\AdminMiddleware::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,  // Base for throttle:api/auth
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'token.expiry' => \App\Http\Middleware\CheckTokenExpiry::class,
            'force.json' => \App\Http\Middleware\ForceJsonResponse::class,  // Alias if not already
            'subscription' => \App\Http\Middleware\CheckSubscription::class,
        ]);

        // Middleware priority (Laravel 12.x recommended—ensures order)
        $middleware->priority([
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,  // If using Redis cache
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withProviders([
        BroadcastServiceProvider::class,
    ])
    ->create();
