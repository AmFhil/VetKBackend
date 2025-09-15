<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
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
        return $user->createToken('auth_token')->plainTextToken;
    }

    // user registration method
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Create a new user
        $user = User::create([
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
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
            Carbon::now()->addMinutes(60),
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
        $url = env('TEXT_url');

        return Redirect::to($url . "/signup/personal-info?token={$token}");
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

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
        $request->validate([
            'userEmail' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->userEmail)->first();

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
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user->email_verified_at) {
            return response()->json(['error' => 'Email already verified'], 400);
        }

        // Generate a new signed URL (valid for 60 minutes)
        $verificationUrl = URL::temporarySignedRoute(
            'verify.email',
            Carbon::now()->addMinutes(60),
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
