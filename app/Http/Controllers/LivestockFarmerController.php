<?php

namespace App\Http\Controllers;

use App\Models\livestock_farmer;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LivestockFarmerController extends Controller
{
    //
    public function AddLivestockFarmer(Request $request)
    {
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($user->activeRole && $user->activeRole->name === 'livestock_farmer') {
            return response()->json(['message' => 'You already have a livestock farmer role.'], 400);
        }

        if ($user->activeRole === 'veterinary_doctor' || $user->activeRole === 'vendor' || $user->activeRole === 'veterinary_paraprofessional' && !$user->hasRole('livestock_farmer')){
            return response()->json(['message' => 'forbidden'], 401);
        }

        if ($user->activeRole->name !== 'livestock_farmer' && $user->hasRole('livestock_farmer')) {
            $activeRole = Role::where('name', 'livestock_farmer')->first();
            if (!$activeRole) {
                return response()->json(['message' => 'Role not found.'], 404);
            }
            $user->roles()->syncWithoutDetaching([$activeRole->id]);
            $user->update([
                'active_role_id' => $activeRole->id
            ]);
            return response()->json(['message' => 'Your active role has been changed to livestock farmer.'], 200);
        }

        DB::beginTransaction();
        try {
            $farmer = Livestock_farmer::create([
                'user_id' => $user->id,
                'role' => 'Livestock Farmer',
            ]);

            // update user's active role
            $activeRole = Role::where('name', 'livestock_farmer')->first();
            if(!$activeRole){
                return response()->json(['message' => 'Role not found.'], 404);
            }
            $user->roles()->attach($activeRole->id);
            $user->update(['active_role_id' => $activeRole->id]);

            $user->logActivity(
                action: 'create_livestock_farmer',
                title: 'Created Livestock Farmer Profile',
                detail: "User {$user->first_name} {$user->last_name} created a Livestock Farmer profile",
                meta: ['livestock_farmer_id' => $farmer->id]
            );

            DB::commit();
            return response()->json([
                'message' => 'Livestock Farmer role activated successfully.',
                'livestock_farmer' => $farmer,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: '.$e->getMessage()], 500);
        }
    }

       public function getLivestockFarmerById($id)
    {
        $livestockFarmer = livestock_farmer::with(['user:id,email,first_name,last_name,phone_num,state,country', 'user.profile:id,bio,profile_image,cover_page_image', 'user.roles:name',
        'user.activeRole:name'])
            ->select('id', 'user_id', 'role')
            ->where('id', $id)
            ->first();

        if (!$livestockFarmer) {
            return response()->json(['message' => 'LivestockFarmer not found.'], 404);
        }

        return response()->json(['livestockFarmer' => $livestockFarmer], 200);
    }

    public function livestockFarmerProfile()
    {
        $user = auth()->user();
        if (!$user){
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $livestockFarmer = livestock_farmer::with(['user:id,email,first_name,last_name,phone_num,state,country', 'user.profile:id,bio,profile_image,cover_page_image', 'user.roles:name',
        'user.activeRole:name'])
            ->select('id', 'user_id', 'role')
            ->where('user_id', $user->id)
            ->first();

        if (!$livestockFarmer) {
            return response()->json(['message' => 'LivestockFarmer not found.'], 404);
        }

        return response()->json(['livestockFarmer' => $livestockFarmer], 200);
    }
}
