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
}
