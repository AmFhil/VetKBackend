<?php

namespace App\Http\Controllers;

use App\Models\Others;
use Illuminate\Http\Request;

class OthersController extends Controller
{
    //
    public function addOthers(Request $request){
        $user = auth()->user();
        if (!$user){
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // validate request
        $validated = $request->validate([
        ]);

        // check if already has profile
        if (Others::where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Profile already exists.'], 409);
        }

        // create livestock farmer profile
        $others = Others::create([
            'user_id' => $user->id,
            'role' => 'Others',
        ]);

        // return response
        return response()->json(['message' => 'Others profile created successfully.', 'data' => $others], 200);
    }
}
