<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\CompleteProfileLoginLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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
        ]);

        // send verification mail with login link and activation mail
        try {
            $user->notify(new CompleteProfileLoginLink('/complete-profile'));
        } catch (\Exception $e) {
            // fallback
            \Illuminate\Support\Facades\Notification::route('mail', $user->email)->notifyNow(new CompleteProfileLoginLink('/complete-profile'));
        }

        // return a response as JSON
        return response()->json(['message' => 'User registered successfully'], 200);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $passwordMatches = false;

        // check bcrypt
        if (Hash::check($validated['password'], $user->password)) {
            $passwordMatches = true;
        }

        // if not bcrypt, check sha1
        elseif ($user->password === sha1($validated['password'])) {
            $passwordMatches = true;

            // upgrade password to bcrypt for future login
            $user->update([
                'password' => Hash::make($validated['password']),
                'password_algorithm' => 'bcrypt',
            ]);
        }

        if (!$passwordMatches) {
            return response()->json(['error' => "Invalid credentials"], 401);
        };

        // update profile if empty or null
        if (empty($user->phone_num) || empty($user->state) || empty($user->country)) {
            $profileData = $request->validate([
                'phone_num' => 'required|string|max:15',
                'state' => 'required|string|max:50',
                'country' => 'required|string|max:50',
            ]);

            // update user profile
            $user->update($profileData);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    // logout function
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            $user->tokens()->delete(); // delete all tokens from the user
            return response()->json(['message' => 'Logged out successfully'], 200);
        } else {
            return response()->json(['error' => 'User not authenticated'], 401);
        }
    }
}
