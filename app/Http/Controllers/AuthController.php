<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\CompleteProfileLoginLink;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private function issueToken(User $user)
    {
        // Just create token – expiry logic is handled by middleware
        return $user->createToken('auth_token')->plainTextToken;
    }

    // user registration method
    public function register(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
            'email' => 'required|email|unique:users_registration,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Create a new user instance
        $user = User::create([
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']), // Hash the password
            'password_algorithm' => 'bcrypt',
            'created_at' => Carbon::now(),
        ]);

        // Signed URL (valid for 60 minutes)
        $verificationUrl = URL::temporarySignedRoute(
            'verify.email',
            Carbon::now()->addMinutes(60),
            ['id' => $user->id]
        );

        // send verification mail with login link and activation mail
        try {
            // $token = $user->createToken('user_token', ['user'])->plainTextToken;

            $user->notify(new CompleteProfileLoginLink($verificationUrl));
        } catch (\Exception $e) {
            // fallback
            // $token = $user->createToken('user_token', ['user'])->plainTextToken;

            \Illuminate\Support\Facades\Notification::route('mail', $user->email)->notifyNow(new CompleteProfileLoginLink($verificationUrl));
        }

        // return a response as JSON
        return response()->json(['message' => 'Verification Link Have Been Sent to Your Mail'], 200);
    }

    public function verifyEmail(Request $request, $id)
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['error' => 'Invalid or expired link'], 401);
        }

        $user = User::findOrFail($id);

        if (! $user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        // Issue expiring token
        $token = $this->issueToken($user);

        return Redirect::to("https://nextjs-frontend-beta-drab.vercel.app/home?token={$token}");
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        Log::info('Input email preview: ' . substr($validated['email'], 0, 5) . '...');  // Safe preview
        Log::info('Input password preview: ' . substr($validated['password'], 0, 5) . '...');

        $user = User::where('email', $validated['email'])->first();
        Log::info('Fetched user password preview: ' . substr($user?->password ?? 'NULL', 0, 10));

        if (!$user) {
            Log::info('Login fail: User not found for email');
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $passwordMatches = Hash::check($validated['password'], $user->password);
        Log::info('Bcrypt check result: ' . ($passwordMatches ? 'true' : 'false'));

        // Legacy SHA1 check (only if not bcrypt)
        if (!$passwordMatches && !str_starts_with($user->password, '$2y$')) {
            Log::info('Attempting SHA1 fallback');
            $sha1Hash = sha1($validated['password']);
            if ($user->password === $sha1Hash) {
                $passwordMatches = true;
                Log::info('SHA1 match - upgrading to bcrypt');

                // Upgrade without mutator
                $bcryptHash = Hash::make($validated['password']);
                $user->setRawAttributes([
                    'password' => $bcryptHash,
                    'password_algorithm' => 'bcrypt',
                ]);
                $user->saveQuietly();
            } else {
                Log::info('SHA1 mismatch (stored preview: ' . substr($user->password, 0, 10) . ')');
            }
        }

        if (!$passwordMatches) {
            Log::info('Login fail: Password mismatch for user ID ' . $user->id);
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        Log::info('Login success for user ID: ' . $user->id);
        $token = $this->issueToken($user);

        return response()->json([
            'message' => 'Login successful',
            $user->makeHidden(['password', 'password_algorithm', 'remember_token'])->toArray(),
            'token' => $token,
        ]);
    }

    // logout function
    public function logout(Request $request)
    {
        $request->validate([
            'userEmail' => 'required|email|exists:users_registration,email',
        ]);

        $user = User::where('email', $request->userEmail)->first();

        if ($user) {
            $user->tokens()->delete();
            return response()->json(['message' => 'Logged out successfully'], 200);
        }

        return response()->json(['error' => 'User not found'], 401);
    }
}
