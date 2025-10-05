<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        Log::info('ForceJsonResponse middleware applied', [
            'url' => $request->url(),
            'acceptHeader' => $request->header('Accept'),
            'contentType' => $request->header('Content-Type'),
            'expectsJson' => $request->expectsJson(),
        ]);
        return $next($request);
    }
}
