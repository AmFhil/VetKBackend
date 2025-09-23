<?php

namespace App\Http\Controllers;

use App\Models\Others;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OthersController extends Controller
{
    //
    public function addOthers(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (Others::where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You already registered as Other role.'], 400);
        }

        DB::beginTransaction();
        try {
            $other = Others::create([
                'user_id' => $user->id,
                'role' => 'Others',
            ]);

            $activeRole = Role::where('name', 'others')->first();
            if (!$activeRole) {
                return response()->json(['message' => 'Role not found.'], 404);
            }
            $user->roles()->attach($activeRole->id);
            $user->update(['active_role_id' => $activeRole->id]);

            DB::commit();
            return response()->json([
                'message' => 'Other role activated successfully.',
                'other' => $other,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function getOtherById($id)
    {
        $other = Others::with([
            'user:id,email,first_name,last_name,phone_num,state,country',
            'user.profile:id,bio,profile_image,cover_page_image',
            'user.roles:name',
            'user.activeRole:name'
        ])
            ->select('id', 'user_id', 'role')
            ->where('id', $id)
            ->first();

        if (!$other) {
            return response()->json(['message' => 'Other not found.'], 404);
        }

        return response()->json(['other' => $other], 200);
    }

    public function otherProfile()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $other = Others::with([
            'user:id,email,first_name,last_name,phone_num,state,country',
            'user.profile:id,bio,profile_image,cover_page_image',
            'user.roles:name',
            'user.activeRole:name'
        ])
            ->select('id', 'user_id', 'role')
            ->where('user_id', $user->id)
            ->first();

        if (!$other) {
            return response()->json(['message' => 'Other not found.'], 404);
        }

        return response()->json(['other' => $other], 200);
    }
}
