<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    //
    public function addVendor(){
        $user = auth()->user();
        if (!$user){
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

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

    // get veterinary clinic by id
    public function getVendorById($id)
    {
        $vendor = Vendor::with(['user:id,email,first_name,last_name,phone_num,state,country', 'user.profile:id,bio,profile_image,cover_page_image'])
            ->select('id', 'user_id', 'role')
            ->where('id', $id)
            ->first();

        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found.'], 404);
        }

        return response()->json(['vendor' => $vendor], 200);
    }

    public function VendorProfile()
    {
        $user = auth()->user();
        if (!$user){
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $vendor = Vendor::with(['user:id,email,first_name,last_name,phone_num,state,country', 'user.profile:id,bio,profile_image,cover_page_image'])
            ->select('id', 'user_id', 'role')
            ->where('id', $user->id)
            ->first();

        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found.'], 404);
        }

        return response()->json(['vendor' => $vendor], 200);
    }
}
