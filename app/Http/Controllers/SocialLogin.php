<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Laravel\Socialite\Facades\Socialite;

class SocialLogin extends AuthController
{
    //
    private function issueToken(User $user)
    {
        $expiration = Carbon::now()->addMinutes(15);
        return $user->createToken('auth_token', ['*'], $expiration)->plainTextToken;
    }

    // verification of email
    private function emailVerified(User $user)
    {
        $user->email_verified_at = now();
        $user->save();
    }

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
                // if (is_null($exitingUser->provider)) {
                //     return response()->json(['error' => 'Email already in use with normal registration'], 409);
                // }

                // if ($exitingUser->provider !== 'google') {
                //     return response()->json(['error' => 'Email already linked with another provider'], 409);
                // }

                // social user exists, login

                // delete old tokens
                $exitingUser->tokens()->delete();
                $token = $this->issueToken($exitingUser);
                $url = env('FRONTEND_URL');

                return Redirect::to($url . "/success?form=Login&token={$token}");
                // return response()->json(['token' => $token, 'user' => $exitingUser], 200);
            }

            // if user does not exit -> create with social data
            $newUser = User::create([
                'email' => $googleUser->getEmail(),
                'provider' => 'google',
                'provider_id' => $googleUser->getId(),
                'password' => null,
            ]);

            $defaultRole = Role::where('name', 'basic_user')->first();
            if ($defaultRole) {
                $newUser->roles()->attach($defaultRole->id);
                $newUser->active_role_id = $defaultRole->id;
                $newUser->save();
            }

            $newUser->logActivity(
                action: 'social_signup',
                title: 'User Registered via Google',
                detail: "User {$newUser->email} registered using Google social login.",
                meta: [],
            );

            $this->emailVerified($newUser);

            $token = $this->issueToken($newUser);
            $url = env('FRONTEND_URL');

            return Redirect::to($url . "/signup/personal-info?token={$token}");
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
                // if (is_null($exitingUser->provider)) {
                //     return response()->json(['error' => 'Email already in use with normal registration'], 409);
                // }

                // if ($exitingUser->provider !== 'linkedin') {
                //     return response()->json(['error' => 'Email already linked with another provider'], 409);
                // }

                // social user exists, login
                $exitingUser->tokens()->delete();
                $token = $this->issueToken($exitingUser);
                $url = env('FRONTEND_URL');

                return Redirect::to($url . "/success?form=Login&token={$token}");
                // return response()->json(['token' => $token, 'user' => $exitingUser], 200);
            }
            // if user does not exit -> create with social data
            $newUser = User::create([
                'email' => $linkedInUser->getEmail(),
                'provider' => 'linkedin',
                'provider_id' => $linkedInUser->getId(),
                'password' => null, // no password for social login
            ]);

            $this->emailVerified($newUser);

            $defaultRole = Role::where('name', 'basic_user')->first();
            if ($defaultRole) {
                $newUser->roles()->attach($defaultRole->id);
                $newUser->active_role_id = $defaultRole->id;
                $newUser->save();
            }

            $token = $this->issueToken($newUser);
            $url = env('FRONTEND_URL');

            return Redirect::to($url . "/signup/personal-info?token={$token}");
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed', 'message' => $e->getMessage()], 500);
        };
    }

    public function completeUserRegistration(Request $request)
    {
        $user = auth('user')->user();
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
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone_num' => $validated['phone_num'],
            'state' => $validated['state'],
            'country' => $validated['country'],
            'update_at' => Carbon::now(),
        ]);

        $user->logActivity(
            'account completed',
            "Completed User Registration",
            "User {$user->first_name} {$user->last_name} completed their registration.",
            [],
        );

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }
}
