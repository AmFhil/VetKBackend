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
            'user_id' => 'required|integer|unique:others,user_id',
        ]);

        // create livestock farmer profile
        $others = Others::create([
            'user_id' => $validated['user_id'],
            'role' => 'others',
        ]);

        // return response
        return response()->json(['message' => 'Others profile created successfully.', 'data' => $others], 200);
    }
}
