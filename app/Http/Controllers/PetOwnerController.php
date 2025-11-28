<?php

namespace App\Http\Controllers;

use App\Models\Pet_Owner;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PetOwnerController extends Controller
{
    //
    public function choosePetOwnerRole(Request $request)
    {
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        DB::beginTransaction();
        // check if already a pet owner
        if ($user->activeRole && $user->activeRole->name === 'pet_owner') {
            DB::rollBack();
            return response()->json(['message' => 'You already have a pet owner role.'], 400);
        }

        if ($user->activeRole === 'veterinary_doctor' || $user->activeRole === 'vendor' || $user->activeRole === 'veterinary_paraprofessional' && !$user->hasRole('pet_owner')) {
            DB::rollBack();
            return response()->json(['message' => 'forbidden'], 401);
        }

        $PetOwnerRole = Role::where('name', 'pet_owner')->first();
        if (!$PetOwnerRole) {
            DB::rollBack();
            return response()->json(['message' => 'Role not found.'], 404);
        }

        if ($user->hasRole('pet_owner')) {
            $user->update([
                'active_role_id' => $PetOwnerRole->id
            ]);
            DB::commit();

            $user->refresh();
            return response()->json(['message' => 'Your active role has been changed to pet owner.'], 200);
        }


        try {
            // create profile
            $petOwner = Pet_Owner::create([
                'user_id' => $user->id,
                'role' => 'Pet Owner',
            ]);

            $user->roles()->attach($PetOwnerRole->id);
            $user->update([
                'active_role_id' => $PetOwnerRole->id,
            ]);

            DB::commit();
            $user->refresh();
            $user->logActivity(
                action: 'create_petOwner',
                title: 'Created Pet Owner Profile',
                detail: "User {$user->first_name} {$user->last_name} created a Pet Owner profile",
                meta: ['petOwner_id' => $petOwner->id]
            );

            return response()->json([
                'message' => 'Pet Owner role activated successfully.',
                'pet_owner' => $petOwner,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function getPetOwnerById($id)
    {
        $petOwner = Pet_Owner::with([
            'user:id,email,first_name,last_name,phone_num,state,country',
            'user.profile:id,bio,profile_image,cover_page_image',
            'user.roles:name',
            'user.activeRole:name'
        ])
            ->select('id', 'user_id', 'role')
            ->where('id', $id)
            ->first();

        if (!$petOwner) {
            return response()->json(['message' => 'PetOwner not found.'], 404);
        }

        return response()->json(['petOwner' => $petOwner], 200);
    }

    public function PetOwnerProfile()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $petOwner = Pet_Owner::with(['user:id,email,first_name,last_name,phone_num,state,country', 'user.profile:id,bio,profile_image,cover_page_image'])
            ->select('id', 'user_id', 'role')
            ->where('user_id', $user->id)
            ->first();

        if (!$petOwner) {
            return response()->json(['message' => 'PetOwner not found.'], 404);
        }

        return response()->json(['petOwner' => $petOwner], 200);
    }
}
