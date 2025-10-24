<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CheckTokenExpiry
{
    public function handle(Request $request, Closure $next)
    {
        $accessToken = $request->user()?->currentAccessToken();

        if (! $accessToken instanceof PersonalAccessToken) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // If expired → block
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            $accessToken->delete();
            return response()->json(['error' => 'Token expired'], 401);
        }

        // Refresh idle timeout → extend expiry 1h from now
        if ($accessToken->expires_at && $accessToken->expires_at->diffInMinutes(now()) < 1) {
            $accessToken->expires_at = Carbon::now()->addMinutes(15);
            $accessToken->save();
        }

        return $next($request);
    }
}
