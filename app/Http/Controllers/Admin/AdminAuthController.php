<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\BroadcastsAdminEvents;
use Illuminate\Http\Request;
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

            $admin = DB::table('admin')->where('staff_id', $request->staff_id)->first();

            if (!$admin) {
                return response()->json(['error' => 'invalid credentials'], 401);
            }

            // check bcrypt
            if (Hash::check($request->password, $admin->password)) {
                $this->passwordMatches = true;
            } elseif ($admin->password === sha1($request->password)) {
                $this->passwordMatches = true;

                // update password to bcrypt
                $admin->update([
                    'password' => Hash::make($request->password)
                ]);
            }

            if (!$this->passwordMatches) {
                return response()->json(['error' => 'invalid credentials'], 401);
            }

            $token = $admin->createToken('admin_token')->plainTextToken;
            $admin->role = 'Admin';
            $admin->profile_picture = ('https://vetkonect.com/backend/storage/app/public/admin_pic/' . $admin->profile_picture);
            return response()->json(['message' => 'Login successful', 'admin' => $admin, 'token' => $token], 200);
        } catch (\Throwable $th) {
            return (['code' => 400, 'detail' => $th->errorInfo[2]]);
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
            $validated['profile_picture'] = basename($imagePath);
        }

        $admin->update($validated);

        $admin->notify(new \App\Notifications\AdminActionNotification(
            'Profile Update',
            'Hi ' . $request->first_name . ',<br>This is to notify you that your profile was updated successfully.'
        ));

        $this->createAdminActivity(
            $admin->id,
            'updated',
            "Admin Updated",
            "Admin {$admin->first_name} This is to notify you that your profile was updated successfully '{$admin->staff_id}'.",
            ['admin_id' => $admin->id]
        );

        return response()->json(['message' => 'Admin details updated successfully', 'data' => $admin]);
    }
}
