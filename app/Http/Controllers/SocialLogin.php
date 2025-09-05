<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialLogin extends AuthController
{
    //
    public function redirectToGoogle()
    {
        // logic to redirect to google for authentication
        return Socialite::driver('google')->stateless()->redirect();
    }

    // handle callback from google
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // check if user with same email already exists
            $exitingUser = User::where('email', $googleUser->getEmail())->first();

            if ($exitingUser) {
                // if exits but not social login, block
                if (is_null($exitingUser->provider)) {
                    return response()->json(['error' => 'Email already in use with normal registration'], 409);
                }

                if ($exitingUser->provider !== 'google') {
                    return response()->json(['error' => 'Email already linked with another provider'], 409);
                }

                // social user exists, login
                $token = $exitingUser->createToken('auth_token')->plainTextToken;
                return response()->json(['token' => $token, 'user' => $exitingUser], 200);
            }

            // if user does not exit -> create with social data
            $newUser = User::create([
                'email' => $googleUser->getEmail(),
                'provider' => 'google',
                'provider_id' => $googleUser->getId(),
                'password' => null, // no password for social login
                'created_at' => Carbon::now(),
            ]);

            $token = $newUser->createToken('auth_token')->plainTextToKen;
            return response()->json(['message' => 'Login successful. Please complete your profile.', 'token' => $token, 'user' => $newUser, 'next_step' => 'complete_profile'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed', 'message' => $e->getMessage()], 500);
        }
    }

    // same setup for linkedIn
    public function redirectToLinkedin()
    {
        return Socialite::driver('linkedin')->stateless()->redirect();
    }

    public function handleLinkedinCallback()
    {
        try {
            $linkedInUser = Socialite::driver('linkedin')->stateless()->user();

            $exitingUser = User::where('email', $linkedInUser->getEmail())->first();

            if ($exitingUser) {
                // if exits but not social login, block
                if (is_null($exitingUser->provider)) {
                    return response()->json(['error' => 'Email already in use with normal registration'], 409);
                }

                if ($exitingUser->provider !== 'linkedin') {
                    return response()->json(['error' => 'Email already linked with another provider'], 409);
                }

                // social user exists, login
                $token = $exitingUser->createToken('auth_token')->plainTextToken;
                return response()->json(['token' => $token, 'user' => $exitingUser], 200);
            }
            // if user does not exit -> create with social data
            $newUser = User::create([
                'email' => $linkedInUser->getEmail(),
                'provider' => 'linkedin',
                'provider_id' => $linkedInUser->getId(),
                'password' => null, // no password for social login
                'created_at' => Carbon::now(),
            ]);

            $token = $newUser->createToken('auth_token')->plainTextToKen;
            return response()->json(['message' => 'Login successful. Please complete your profile.', 'token' => $token, 'user' => $newUser, 'next_step' => 'complete_profile'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed', 'message' => $e->getMessage()], 500);
        };
    }

    public function completeUserRegistration(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:50|min:3',
            'last_name' => 'required|string|max:50|min:3',
            'phone_num' => 'required|string|max:15',
            'state' => 'required|string|max:50',
            'country' => 'required|string|max:50',
        ]);

        $user = $request->user();
        $user->update([
            'email_verified_at' => Carbon::now(),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone_num' => $validated['phone_num'],
            'state' => $validated['state'],
            'country' => $validated['country'],
            'update_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }
}
