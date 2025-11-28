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

        DB::beginTransaction();
        if ($user->activeRole && $user->activeRole->name === 'livestock_farmer') {
            DB::rollBack();
            return response()->json(['message' => 'You already have a livestock farmer role.'], 400);
        }

        if ($user->activeRole === 'veterinary_doctor' || $user->activeRole === 'vendor' || $user->activeRole === 'veterinary_paraprofessional' && !$user->hasRole('livestock_farmer')){
            DB::rollBack();
            return response()->json(['message' => 'forbidden'], 401);
        }

        $livestockFarmerRole = Role::where('name', 'livestock_farmer')->first();
            if (!$livestockFarmerRole) {
                return response()->json(['message' => 'Role not found.'], 404);
            }

        if ($user->hasRole('livestock_farmer')) {
            $user->update([
                'active_role_id' => $livestockFarmerRole->id
            ]);
            DB::commit();

            $user->refresh();
            return response()->json(['message' => 'Your active role has been changed to livestock farmer.'], 200);
        }


        try {
            $farmer = Livestock_farmer::create([
                'user_id' => $user->id,
                'role' => 'Livestock Farmer',
            ]);

            // update user's active role
            $user->roles()->attach($livestockFarmerRole->id);
            $user->update(['active_role_id' => $livestockFarmerRole->id]);

            $user->logActivity(
                action: 'create_livestock_farmer',
                title: 'Created Livestock Farmer Profile',
                detail: "User {$user->first_name} {$user->last_name} created a Livestock Farmer profile",
                meta: ['livestock_farmer_id' => $farmer->id]
            );

            DB::commit();
            $user->refresh();
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
