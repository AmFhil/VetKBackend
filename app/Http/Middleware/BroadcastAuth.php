<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BroadcastAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // user guard
        if (auth('sanctum')->check()) {
            return $next($request);
        }

        // admin guard
        if (auth('admin')->check()) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthenticated'], 401);
    }
}
