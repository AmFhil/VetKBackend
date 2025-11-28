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

        DB::beginTransaction();
        if ($user->activeRole && $user->activeRole->name === 'others') {
            DB::rollBack();
            return response()->json(['message' => 'You already have a others role.'], 400);
        }

        if ($user->activeRole === 'veterinary_doctor' || $user->activeRole === 'vendor' || $user->activeRole === 'veterinary_paraprofessional' && !$user->hasRole('others')) {
            DB::rollBack();
            return response()->json(['message' => 'forbidden'], 401);
        }

        $othersRole = Role::where('name', 'others')->first();
        if (!$othersRole) {
            DB::rollBack();
            return response()->json(['message' => 'Role not found.'], 404);
        }

        if ($user->hasRole('others')) {
            $user->update([
                'active_role_id' => $othersRole->id
            ]);
            DB::commit();

            $user->refresh();
            return response()->json(['message' => 'Your active role has been changed to others.'], 200);
        }

        try {
            $other = Others::create([
                'user_id' => $user->id,
                'role' => 'Others',
            ]);

            $user->roles()->attach($othersRole->id);
            $user->update(['active_role_id' => $othersRole->id]);

            $user->logActivity(
                action: 'create_others',
                title: 'Created Others Profile',
                detail: "User {$user->first_name} {$user->last_name} created a Others profile",
                meta: ['others_id' => $other->id]
            );
            DB::commit();
            $user->refresh();
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
