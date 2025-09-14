<?php

namespace App\Http\Controllers;

use App\Models\Pet_Owner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PetOwnerController extends Controller
{
    //
    public function choosePetOwnerRole(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // check if already a pet owner
        if (Pet_Owner::where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You already registered as a Pet Owner.'], 400);
        }

        DB::beginTransaction();
        try {
            // create profile
            $petOwner = Pet_Owner::create([
                'user_id' => $user->id,
                'role' => 'Pet Owner',
            ]);

            // update user's active role
            $user->update([
                'active_role_id' => $petOwner->id, // or store role name depending on your schema
            ]);

            DB::commit();
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
        $petOwner = Pet_Owner::with(['user:id,email,first_name,last_name,phone_num,state,country', 'user.profile:id,bio,profile_image,cover_page_image'])
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
        if (!$user){
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $petOwner = Pet_Owner::with(['user:id,email,first_name,last_name,phone_num,state,country', 'user.profile:id,bio,profile_image,cover_page_image'])
            ->select('id', 'user_id', 'role')
            ->where('id', $user->id)
            ->first();

        if (!$petOwner) {
            return response()->json(['message' => 'PetOwner not found.'], 404);
        }

        return response()->json(['petOwner' => $petOwner], 200);
    }
}
