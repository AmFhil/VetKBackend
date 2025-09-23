<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorController extends Controller
{
    //
    public function addVendor()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (Vendor::where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You already registered as a Vendor.'], 400);
        }

        DB::beginTransaction();
        try {
            $vendor = Vendor::create([
                'user_id' => $user->id,
                'role' => 'Vendor',
            ]);

            $activeRole = Role::where('name', 'vendor')->first();
            if(!$activeRole){
                return response()->json(['message' => 'Role not found.'], 404);
            }
            $user->roles()->attach($activeRole->id);
            $user->update(['active_role_id' => $activeRole->id]);

            DB::commit();
            $user->logActivity(
                action: 'create_vendor',
                title: 'Created Vendor Profile',
                detail: "User {$user->first_name} {$user->last_name} created a vendor profile",
                meta: ['vendor_id' => $vendor->id]
            );

            return response()->json([
                'message' => 'Vendor role activated successfully.',
                'vendor' => $vendor,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
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
        if (!$user) {
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

    public function getVendor()
    {
        $vendor = Vendor::with(['user:id,email,first_name,last_name,phone_num,state,country', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'role',  'created_at')
            ->paginate(20);

        return response()->json($vendor, 200);
    }




}
