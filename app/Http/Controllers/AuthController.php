<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

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
        $user = User::create([
            'email' => $validatedData['email'],
            // 'phone_num' => $validatedData['phone_num'],
            // 'state' => $validatedData['state'],
            // 'country' => $validatedData['country'],
            'password' => sha1(($validatedData['password'])), // Hash the password
        ]);

        // return a response as JSON
        return response()->json(['message' => 'User registered successfully', 'user' => $user], 200);
    }
}
