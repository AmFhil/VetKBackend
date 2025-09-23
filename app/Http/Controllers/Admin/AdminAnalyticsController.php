<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Farm;
use App\Models\livestock_farmer;
use App\Models\Others;
use App\Models\Pet;
use App\Models\Pet_Owner;
use App\Models\Products;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VeterinaryClinic;
use App\Models\VeterinaryDoctor;
use App\Models\VeterinaryParaprofessional;
use App\Traits\BroadcastsAdminEvents;
use stdClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminAnalyticsController extends Controller
{
    //
    use BroadcastsAdminEvents;




    // count of all tables in the database
     /**
     * Returns counts across main entities (cached)
     */
    public function adminGetUserCount()
    {
        $counts = Cache::remember('admin_user_counts', now()->addMinutes(5), function () {
            return [
                'veterinaryDoctor' => VeterinaryDoctor::count(),
                'veterinaryClinic' => VeterinaryClinic::count(),
                'veterinaryParaprofessional' => VeterinaryParaprofessional::count(),
                'pet_owner' => Pet_Owner::count(),
                'vendor' => Vendor::count(),
                'livestock_farmer' => livestock_farmer::count(),
                'other' => Others::count(),
                'users' => User::count(),
                'pets' => Pet::count(),
                'farms' => Farm::count(),
                'clinics' => Clinic::count(),
                'stores' => Store::count(),
                'products' => Products::count(),
            ];
        });

        return response()->json($counts);
    }

    /**
     * Lightweight endpoint to refresh cached analytics (admin only)
     */
    public function refreshCountsCache(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        Cache::forget('admin_user_counts');

        // Recompute and return
        return $this->adminGetUserCount();
    }





}
