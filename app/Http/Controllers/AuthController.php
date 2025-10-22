<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Notifications\CompleteProfileLoginLink;
use App\Notifications\VerifyEmailToLogin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    private function issueToken(User $user)
    {
        return $user->createToken('auth_token')->plainTextToken;
    }

    // user registration method
    public function register(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            'email' => 'required|email|string|lowercase|unique:users_registration,email',
            'password' => ['required', 'string', 'min:8', 'confirmed', Rules\Password::defaults()]
        ]);

        if ($validatedData->fails()) {
            // Check if the validation error is specifically for the email's unique rule
            if ($validatedData->errors()->has('email') && in_array('The email has already been taken.', $validatedData->errors()->get('email'))) {
                return response()->json([
                    'message' => 'This email is already registered. Please go and login.'
                ], 409); // Use a 409 Conflict status code
            }

            // If other validation errors exist, return the default error response
            throw new ValidationException($validatedData);
        }

        // Create a new user
        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'password_algorithm' => 'bcrypt',
            'created_at' => Carbon::now(),
        ]);

        // Assign default role
        $defaultRole = Role::where('name', 'basic_user')->first();
        if ($defaultRole) {
            $user->roles()->attach($defaultRole->id);
            $user->active_role_id = $defaultRole->id;
            $user->save();
        }

        // Signed URL (valid for 60 minutes)
        $verificationUrl = URL::temporarySignedRoute(
            'verify.email',
            Carbon::now()->addMinutes(1),
            ['id' => $user->id]
        );

        try {
            $user->notify(new CompleteProfileLoginLink($verificationUrl));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Notification::route('mail', $user->email)
                ->notifyNow(new CompleteProfileLoginLink($verificationUrl));
        }

        return response()->json(['message' => 'Verification Link sent to your email', 'email_verified_at' => $user->email_verified_at], 200);
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

        $token = $this->issueToken($user);
        $url = env('FRONTEND_URL');

        return Redirect::to($url . "/signup/personal-info?token={$token}");
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $passwordMatches = false;

        // Check for legacy SHA1 format
        if (strlen($user->password) === 40 || $user->password_algorithm === 'sha1' || $user->password_algorithm === null) {
            if (sha1($validated['password']) === $user->password) {
                $passwordMatches = true;
                // Rehash to bcrypt immediately
                $user->password = Hash::make($validated['password']);
                $user->password_algorithm = 'bcrypt';
                $user->save();
            }
        } else {
            // Modern bcrypt check
            if (Hash::check($validated['password'], $user->password)) {
                $passwordMatches = true;
                // Optional: Rehash if needed (e.g., if bcrypt cost factor changed)
                if (Hash::needsRehash($user->password)) {
                    $user->password = Hash::make($validated['password']);
                    $user->save();
                }
            }
        }

        if (! $passwordMatches) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        if ($user->email_verified_at === null) {
            // Signed URL (valid for 5 minutes)
            $verificationUrl = URL::temporarySignedRoute(
                'verify.email',
                Carbon::now()->addMinutes(1),
                ['id' => $user->id]
            );

            try {
                $user->notify(new VerifyEmailToLogin($verificationUrl));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Notification::route('mail', $user->email)
                    ->notifyNow(new VerifyEmailToLogin($verificationUrl));
            }

            return response()->json(['error' => "Your account isn't verified yet. Please check your inbox for the verification link and complete verification to log in."], 401);
        }

        // delete old tokens
        $user->tokens()->delete();

        $token = $this->issueToken($user);

        return response()->json([
            'message' => 'Login successful',
            'user' => $user->makeHidden(['password', 'password_algorithm', 'remember_token']),
            'roles' => $user->roles()->pluck('name'), // all roles
            'active_role' => $user->activeRole?->name,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $user = auth('user')->user();

        if ($user) {
            $user->tokens()->delete();
            return response()->json(['message' => 'Logged out successfully'], 200);
        }

        return response()->json(['error' => 'User not found'], 401);
    }

    // Resend verification email
    public function resendVerificationEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users_registration,email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user->email_verified_at) {
            return response()->json(['error' => 'Email already verified'], 400);
        }

        // Generate a new signed URL (valid for 60 minutes)
        $verificationUrl = URL::temporarySignedRoute(
            'verify.email',
            Carbon::now()->addMinutes(1),
            ['id' => $user->id]
        );

        try {
            $user->notify(new CompleteProfileLoginLink($verificationUrl));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Notification::route('mail', $user->email)
                ->notifyNow(new CompleteProfileLoginLink($verificationUrl));
        }

        return response()->json(['message' => 'Verification link resent to your email'], 200);
    }
}
