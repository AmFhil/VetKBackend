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

       public function getOtherById($id)
    {
        $other = Others::with(['user:id,email,first_name,last_name,phone_num,state,country', 'user.profile:id,bio,profile_image,cover_page_image'])
            ->select('id', 'user_id', 'role')
            ->where('id', $id)
            ->first();

        if (!$other) {
            return response()->json(['message' => 'Other not found.'], 404);
        }

        return response()->json(['other' => $other], 200);
    }

    public function otherProfile()
    {
        $user = auth()->user();
        if (!$user){
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $other = Others::with(['user:id,email,first_name,last_name,phone_num,state,country', 'user.profile:id,bio,profile_image,cover_page_image'])
            ->select('id', 'user_id', 'role')
            ->where('id', $user->id)
            ->first();

        if (!$other) {
            return response()->json(['message' => 'Other not found.'], 404);
        }

        return response()->json(['other' => $other], 200);
    }
}
