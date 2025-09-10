<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle($request, Closure $next, ...$guard)
    {
        if (Auth::guard($guard)->check()) {
            return response()->json(['message' => 'Already authenticated'], 200);
        }

        return $next($request);
    }
}
