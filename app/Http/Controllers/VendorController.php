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
    $user = auth('user')->user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    DB::beginTransaction();

    try {

        // already a vendor AND active
        if ($user->activeRole && $user->activeRole->name === 'vendor') {
            DB::rollBack();
            return response()->json(['message' => 'You are already using the vendor role.'], 400);
        }

        $vendorRole = Role::where('name', 'vendor')->first();
        if (!$vendorRole) {
            DB::rollBack();
            return response()->json(['message' => 'Vendor role not found.'], 404);
        }

        // If user already has vendor role → just switch active role
        if ($user->hasRole('vendor')) {

            $user->update(['active_role_id' => $vendorRole->id]);

            DB::commit();

            // refresh so activeRole reflects new value
            $user->refresh();

            return response()->json([
                'message' => 'Your active role has been changed to vendor.',
                'active_role' => $user->activeRole->name
            ], 200);
        }

        // If user does NOT have vendor profile → create it
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'role' => 'vendor',
        ]);

        // attach vendor role
        $user->roles()->attach($vendorRole->id);

        // update active role
        $user->update(['active_role_id' => $vendorRole->id]);

        DB::commit();

        $user->refresh();

        $user->logActivity(
            action: 'create_vendor',
            title: 'Created Vendor Profile',
            detail: "User {$user->first_name} {$user->last_name} created a vendor profile",
            meta: ['vendor_id' => $vendor->id]
        );

        return response()->json([
            'message' => 'Vendor role activated successfully.',
            'vendor' => $vendor,
            'active_role' => $user->activeRole->name
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
    }
}


    // get veterinary clinic by id
    public function getVendorById($id)
    {
        $vendor = Vendor::with([
            'user:id,email,first_name,last_name,phone_num,state,country',
            'user.profile:id,bio,profile_image,cover_page_image',
            'user.roles:name',
            'user.activeRole:name',
            'user.media:id,user_id,file_path'
        ])
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

        $vendor = Vendor::with(['user:id,email,first_name,last_name,phone_num,state,country', 'user.profile:id,bio,profile_image,cover_page_image', 'user.roles:name',
        'user.activeRole:name'])
            ->select('id', 'user_id', 'role')
            ->where('user_id', $user->id)
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
