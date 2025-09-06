<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    //
    public function addVendor(Request $request){
        $user = auth()->user();
        if (!$user){
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // validate request
        $validated = $request->validate([
        ]);

        // check if already has profile
        if (Vendor::where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Profile already exists.'], 409);
        }

        // create vendor profile
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'role' => 'Vendor',
        ]);

        // return response
        return response()->json(['message' => 'Vendor profile created successfully.', 'data' => $vendor], 200);
    }
}
