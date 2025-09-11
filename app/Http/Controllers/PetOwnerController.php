<?php

namespace App\Http\Controllers;

use App\Models\Pet_Owner;
use Illuminate\Http\Request;

class PetOwnerController extends Controller
{
    //
    public function addPetOwner(Request $request){
        $user = auth()->user();
        if (!$user){
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // validate request
        $validated = $request->validate([
        ]);

        // check if already has profile
        if (Pet_Owner::where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Profile already exists.'], 409);
        }

        // create pet owner profile
        $petOwner = Pet_Owner::create([
            'user_id' => $user->id,
            'role' => 'Pet Owner',
        ]);

        // return response
        return response()->json(['message' => 'Pet Owner profile created successfully.', 'data' => $petOwner], 200);
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
