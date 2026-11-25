<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Traits\BroadcastsAdminEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    use BroadcastsAdminEvents;
    //
    public $passwordMatches = false;

    public function adminLogin(Request $request)
    {
        try {
            $request->validate([
                'staff_id' => 'required',
                'password' => 'required'
            ]);

            // Use the Admin model for better functionality
            $admin = Admin::where('staff_id', $request->staff_id)->first();

            if (!$admin) {
                return response()->json(['error' => 'Invalid credentials'], 401);
            }

            // Check if password matches a bcrypt hash.
            if (!Hash::check($request->password, $admin->password)) {

                // If not, check if the password matches a SHA1 hash.
                if ($admin->password === sha1($request->password)) {
                    // Update password to bcrypt.
                    $admin->password = Hash::make($request->password);
                    $admin->save();
                } else {
                    // If no match, return invalid credentials
                    return response()->json(['error' => 'Invalid credentials'], 401);
                }
            }

            // delete old tokens
            $admin->tokens()->delete();

            // Generate the token and return a successful response.
            $expiration = Carbon::now()->addMinutes(30);
            $token = $admin->createToken('admin_token', ['*'], $expiration)->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'admin' => $admin,
                'token' => $token
            ], 200);
        } catch (\Throwable $th) {
            // A more specific error handling is recommended for production.
            return response()->json([
                'code' => 500,
                'detail' => 'An unexpected error occurred.'
            ], 500);
        }
    }

    public function adminLogout(Request $request)
    {
        $admin = auth('admin')->user();
        if ($admin) {
            $admin->tokens()->delete();
            return response()->json(['message' => 'Logged out successfully'], 200);
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
    }

    // update admin details
    public function updateAdminDetails(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => "Unauthorized"], 403);

        $validated = $request->validate([
            'email' => 'sometimes|email|unique:admin,email,' . $admin->id,
            'first_name' => 'sometimes|string|max:50',
            'last_name' => 'sometimes|string|max:50',
            'phone_number' => 'sometimes|string|max:15',
            'location' => 'sometimes|string|max:100',
            'profile_picture' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        if ($request->hasFile('profile_picture')) {
            $imagePath = $request->file('profile_picture')->store('admin_pic', 'public');
            $validated['profile_picture'] = $imagePath;
        }

        $admin->update($validated);

        $admin->notify(new \App\Notifications\AdminActionNotification(
            'Profile Update',
            'Hi ' . $request->first_name . ',<br>This is to notify you that your profile was updated successfully.'
        ));

        Cache::forget("admin_details_{$admin->id}");

        $this->createAdminActivity(
            $admin->id,
            'updated',
            "Admin Updated",
            "Admin {$admin->first_name} This is to notify you that your profile was updated successfully '{$admin->staff_id}'.",
            ['admin_id' => $admin->id]
        );

        return response()->json(['message' => 'Admin details updated successfully', 'data' => $admin]);
    }

    // get admin details
    public function getAdminDetails(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => "Unauthorized"], 403);

        // cache admin details
        $adminDetails = Cache::remember("admin_details_{$admin->id}", 60, function () use ($admin) {
            $admin->refresh();
            return $admin;
        });

        return response()->json(['data' => $adminDetails]);
    }
}
