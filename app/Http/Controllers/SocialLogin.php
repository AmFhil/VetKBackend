<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialLogin extends Controller
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
        try{
            $googleUser = Socialite::driver('google')->stateless()->user();

            // check if user with same email already exists
            $exitingUser = User::where('email', $googleUser->getEmail())->first();

            if($exitingUser){
                // if exits but not social login, block
                if(is_null($exitingUser->provider)) {
                    return response()->json(['error' => 'Email already in use with normal registration'], 409);
                }

                if($exitingUser->provider !== 'google'){
                    return response()->json(['error' => 'Email already linked with another provider'], 409);
                }

                // social user exists, login
                $token = $exitingUser->createToken('auth_token')->plainTextToken;
                return response()->json(['token' => $token, 'user' => $exitingUser], 200);

                // if user does not exit -> create with social data
                $newUser = User::create([
                    'email' => $googleUser->getEmail(),
                    'provider' => 'google',
                    'provider_id' => $googleUser->getId(),
                    'password' => null, // no password for social login
                ]);

                $token = $newUser->createToken('auth_token')->plainTextToKen;
                return response()->json(['message' => 'Login successful. Please complete your profile.', 'token' => $token, 'user' => $newUser, 'next_step' => 'complete_profile'], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed', 'message' => $e->getMessage()], 500);
        }
    }

    // same setup for linkedIn
    public function redirectToLinkedin(){
        return Socialite::driver('linkedin')->stateless()->redirect();
    }

    public function handleLinkedinCallback()
    {
        try{
        $linkedInUser = Socialite::driver('linkedin')->stateless()->user();

        $exitingUser = User::where('email', $linkedInUser->getEmail())->first();

        if($exitingUser){
                // if exits but not social login, block
                if(is_null($exitingUser->provider)) {
                    return response()->json(['error' => 'Email already in use with normal registration'], 409);
                }

                if($exitingUser->provider !== 'linkedin'){
                    return response()->json(['error' => 'Email already linked with another provider'], 409);
                }

                // social user exists, login
                $token = $exitingUser->createToken('auth_token')->plainTextToken;
                return response()->json(['token' => $token, 'user' => $exitingUser], 200);

                // if user does not exit -> create with social data
                $newUser = User::create([
                    'email' => $linkedInUser->getEmail(),
                    'provider' => 'google',
                    'provider_id' => $linkedInUser->getId(),
                    'password' => null, // no password for social login
                ]);

                $token = $newUser->createToken('auth_token')->plainTextToKen;
                return response()->json(['message' => 'Login successful. Please complete your profile.', 'token' => $token, 'user' => $newUser, 'next_step' => 'complete_profile'], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed', 'message' => $e->getMessage()], 500);
        };
    }

    public function completeProfile(Request $request)
        {
            $validated = $request->validate([
                'phone_num' => 'required|string|max:15',
                'state' => 'required|string|max:50',
                'country' => 'required|string|max:50',
            ]);

            $user = $request->user();
            $user->update($validated);

            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => $user,
            ]);
        }
}
