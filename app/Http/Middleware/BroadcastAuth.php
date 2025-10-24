<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BroadcastAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $rawBody = $request->getContent();

        Log::info('BroadcastAuth Raw Input', [
            'raw_body' => $rawBody,
            'content_type' => $request->header('Content-Type'),
            'headers' => $request->headers->all(),
            'method' => $request->method(),
            'path' => $request->path()
        ]);

        if (!$token) {
            Log::warning('BroadcastAuth: No token provided', ['path' => $request->path()]);
            return response()->json(['message' => 'Token missing'], 401);
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            Log::warning('BroadcastAuth: Invalid token', ['path' => $request->path()]);
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $user = $accessToken->tokenable;

        if (!$user || !($user instanceof User || $user instanceof Admin)) {
            Log::warning('BroadcastAuth: Invalid user type', [
                'path' => $request->path(),
                'user_type' => get_class($user ?? 'null')
            ]);
            return response()->json(['message' => 'Invalid user type'], 401);
        }

        Auth::setUser($user);

        $parsedBody = $request->all();
        if (empty($parsedBody) && $rawBody) {
            $parsedBody = json_decode($rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('BroadcastAuth: JSON parse error', [
                    'error' => json_last_error_msg(),
                    'raw_body' => $rawBody
                ]);
                return response()->json(['message' => 'Invalid JSON payload: ' . json_last_error_msg()], 400);
            }
            $request->merge($parsedBody);
        }

        Log::info('BroadcastAuth Middleware', [
            'user_id' => $user->id,
            'user_type' => get_class($user),
            'path' => $request->path(),
            'channel_name' => $request->input('channel_name'),
            'socket_id' => $request->input('socket_id'),
            'request_body' => $request->all(),
            'raw_body' => $rawBody
        ]);

        // ✅ Log before passing to next middleware
        Log::info('BroadcastAuth: Passing to next middleware', ['path' => $request->path()]);

        $response = $next($request);

        // ✅ Log the response
        Log::info('BroadcastAuth: Response', [
            'status' => $response->getStatusCode(),
            'content' => $response->getContent()
        ]);

        return $response;
    }
}
