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
            'user_id' => 'required|integer|unique:pet_owner,user_id',
        ]);

        // create pet owner profile
        $petOwner = Pet_Owner::create([
            'user_id' => $validated['user_id'],
            'role' => 'Pet Owner',
        ]);

        // return response
        return response()->json(['message' => 'Pet Owner profile created successfully.', 'data' => $petOwner], 200);
    }
}
