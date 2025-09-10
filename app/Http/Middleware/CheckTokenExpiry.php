<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\PersonalAccessToken;

class CheckTokenExpiry
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $accessToken = $request->user()?->currentAccessToken();

        if (! $accessToken instanceof PersonalAccessToken) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // If expired → block
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            $accessToken->delete(); // this works, it's Eloquent
            return response()->json(['error' => 'Token expired'], 401);
        }

        // Refresh idle timeout → extend expiry 1h from now
        $accessToken->expires_at = now()->addHours(1);
        $accessToken->save();

        return $next($request);
    }
}
