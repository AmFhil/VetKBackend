<?php

namespace App\Http;

use App\Http\Middleware\CheckTokenExpiry;
use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [
        // Your global middleware (unchanged—runs on every request)
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    // Empty groups—now in bootstrap/app.php
    protected $middlewareGroups = [
        'web' => [],
        'api' => [],
    ];

    // Empty aliases—now in bootstrap/app.php
    protected $routeMiddleware = [
        // Keep custom ones if not aliased elsewhere
        'token.expiry' => CheckTokenExpiry::class,
    ];
}
