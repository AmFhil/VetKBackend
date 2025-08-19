<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // user registration method
    public function register(Request $request){
        // Validate the request data
        $validatedData = $request->validate([
            'email' => 'required|email|unique:users_registration,email',
            // 'phone_num' => 'required|string|max:15',
            // 'state' => 'required|string|max:50',
            // 'country' => 'required|string|max:50',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Create a new user instance
         User::create([
            'email' => $validatedData['email'],
            // 'phone_num' => $validatedData['phone_num'],
            // 'state' => $validatedData['state'],
            // 'country' => $validatedData['country'],
            'password' => Hash::make($validatedData['password']), // Hash the password
        ]);

        // return a response as JSON
        return response()->json(['message' => 'User registered successfully'], 200);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'phone_num' => 'required|string|max:15',
            'state' => 'required|string|max:50',
            'country' => 'required|string|max:50',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if(!$user){
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $passwordMatches = false;

        // check bcrypt
        if(Hash::check($validated['password'], $user->password)){
            $passwordMatches = true;
        }

        // if not bcrypt, check sha1
        elseif($user->password === sha1($validated['password'])){
            $passwordMatches = true;

            // upgrade password to bcrypt for future login
            $user->update([
                'password' => Hash::make($validated['password'])
            ]);
        }

        if(!$passwordMatches){
            return response()->json(['error' => "Invalid credentials"], 401);
        };

        // update profile if empty or null
        if(empty($user->phone_num) || is_null($user->phone_num) || empty($user->state) || is_null($user->state) || empty($user->country) || is_null($user->country)){
            $user->update([
                'phone_num' => $validated['phone_num'],
                'state' => $validated['state'],
                'country' => $validated['country'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }
}
