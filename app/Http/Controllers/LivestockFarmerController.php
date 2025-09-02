<?php

namespace App\Http\Controllers;

use App\Models\livestock_farmer;
use Illuminate\Http\Request;

class LivestockFarmerController extends Controller
{
    //
    public function AddLivestockFarmer(Request $request){
        $user = auth()->user();
        if (!$user){
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // validate request
        $validated = $request->validate([
            'user_id' => 'required|integer|unique:livestock_farmer,user_id',
        ]);

        // create livestock farmer profile
        $livestockFarmer = livestock_farmer::create([
            'user_id' => $validated['user_id'],
            'role' => 'livestock_farmer',
        ]);

        // return response
        return response()->json(['message' => 'Livestock Farmer profile created successfully.', 'data' => $livestockFarmer], 200);
    }
}
