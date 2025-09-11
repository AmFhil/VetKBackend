<?php

namespace App\Http\Controllers;

use App\Models\livestock_farmer;
use Illuminate\Http\Request;

class LivestockFarmerController extends Controller
{
    //
    public function AddLivestockFarmer(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // validate request (no need for user_id anymore)
        $validated = $request->validate([]);

        // check if already has profile
        if (livestock_farmer::where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Profile already exists.'], 409);
        }

        // create livestock farmer profile
        $livestockFarmer = livestock_farmer::create([
            'user_id' => $user->id,
            'role' => 'Livestock Farmer',
        ]);

        return response()->json([
            'message' => 'Livestock Farmer profile created successfully.',
            'data' => $livestockFarmer
        ], 200);
    }

       public function getLivestockFarmerById($id)
    {
        $livestockFarmer = livestock_farmer::with(['user:id,email,first_name,last_name,phone_num,state,country', 'user.profile:id,bio,profile_image,cover_page_image'])
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

        $livestockFarmer = livestock_farmer::with(['user:id,email,first_name,last_name,phone_num,state,country', 'user.profile:id,bio,profile_image,cover_page_image'])
            ->select('id', 'user_id', 'role')
            ->where('id', $user->id)
            ->first();

        if (!$livestockFarmer) {
            return response()->json(['message' => 'LivestockFarmer not found.'], 404);
        }

        return response()->json(['livestockFarmer' => $livestockFarmer], 200);
    }
}
