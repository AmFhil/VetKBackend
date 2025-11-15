<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Role;
use App\Models\User;
use App\Models\VeterinaryClinic;
use App\Traits\BroadcastsAdminEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VeterinaryClinicController extends Controller
{
    use BroadcastsAdminEvents;
    //
    public function addVeterinaryClinic(Request $request)
    {
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        DB::beginTransaction();

        if ($user->activeRole && $user->activeRole->name === 'veterinary_clinic') {
            return response()->json(['message' => 'You already have a veterinary clinic role.'], 400);
        }

        if ($user->activeRole->name !== 'veterinary_clinic' && $user->hasRole('veterinary_clinic')) {
            $activeRole = Role::where('name', 'veterinary_clinic')->first();
            if (!$activeRole) {
                return response()->json(['message' => 'Role not found.'], 404);
            }
            $user->roles()->syncWithoutDetaching([$activeRole->id]);
            $user->update([
                'active_role_id' => $activeRole->id
            ]);
            return response()->json(['message' => 'Your active role has been changed to veterinary clinic.'], 200);
        }

        // validate request
        $validated = $request->validate([
            'practice_license_num' => 'required|string|max:100|unique:veterinary_clinic,practice_license_num',
            'clinic_name' => 'required|string|max:100',
            'specialty' => 'required|string|max:255',
            'list_them' => 'required|string|max:255',
            'contact_num' => 'required|string|max:15',
            'address' => 'required|string|max:500',
            'longitude' => 'required|numeric',
            'latitude' => 'required|numeric',
        ]);


        try {
            $clinic = VeterinaryClinic::create([
                'user_id' => $user->id,
                'practice_license_num' => $validated['practice_license_num'],
                'name_of_clinic' => $validated['clinic_name'],
                'specialty' => $validated['specialty'],
                'list_them' => $validated['list_them'],
                'contact_num' => $validated['contact_num'],
                'address' => $validated['address'],
                'longitude' => $validated['longitude'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'role' => 'Veterinary Clinic',
                'is_approved' => 0, // default to pending
                'created_at' => Carbon::now(),
            ]);

            $activeRole = Role::where('name', 'veterinary_clinic')->first();
            if (!$activeRole) {
                return response()->json(['message' => 'Role not found.'], 404);
            }
            $user->roles()->attach($activeRole->id);
            $user->update([
                'active_role_id' => $activeRole->id,
            ]);

            DB::commit();
            $user->logActivity(
                action: 'create_veterinary_clinic',
                title: 'Created Veterinary Clinic Profile',
                detail: "User {$user->first_name} {$user->last_name} created a veterinary clinic profile",
                meta: ['veterinary_clinic_id' => $clinic->id]
            );


            // notify admins
            $admins = Admin::all();
            foreach ($admins as $admin) {
                $this->createAdminNotification(
                    $admin->id,
                    "New Veterinary Clinic Pending Approval",
                    "vet_profile",
                    "A new veterinary clinic profile has been created by {$user->first_name} {$user->last_name}.",
                    ['clinic_id' => $clinic->id, 'user_id' => $user->id, 'staff_id' => $admin->staff_id]
                );
            }

            return response()->json(['message' => 'Veterinary clinic added successfully. Pending approval.', 'clinic' => $clinic], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    // get all veterinary clinics
    public function getAllVeterinaryClinics()
    {
        $clinics = VeterinaryClinic::with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'name_of_clinic', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability', 'is_approved', 'created_at')
            ->inRandomOrder()
            ->paginate(10);

        // add average rating to each veterinary clinic
        $clinics->getCollection()->transform(function ($clinic) {
            $clinic->average_rating = $clinic->averageRating();
            return $clinic;
        });

        return response()->json(['message' => "success", 'clinics' => $clinics], 200);
    }

    // get veterinary clinics base on search query
    public function searchVeterinaryClinics(Request $request)
    {
        $query = $request->input('query', '');

        if (!$query) {
            return response()->json(['clinics' => []], 200);
        }

        $clinics = VeterinaryClinic::with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->when($query, function ($q) use ($query) {
                $q->where('specialty', 'like', "%$query%")
                    ->orWhere('name_of_clinic', 'like', "%$query%")
                    ->orWhere('list_them', 'like', "%$query%")
                    ->orWhere('address', 'like', "%$query%");
            })
            ->select('id', 'user_id', 'name_of_clinic', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability', 'is_approved')
            ->paginate(10);

        // add average rating to each veterinary clinic
        $clinics->getCollection()->transform(function ($clinic) {
            $clinic->average_rating = $clinic->averageRating();
            return $clinic;
        });

        return response()->json(['clinics' => $clinics], 200);
    }

    // get veterinary clinics near a location
    public function getNearbyVeterinaryClinics(Request $request)
    {
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        if (!$latitude || !$longitude) {
            return response()->json(['message' => 'Latitude and Longitude are required.'], 400);
        }

        $veterinaryClinics = VeterinaryClinic::with([
            'user:id,email,first_name,last_name,phone_num,state,country',
            'user.profile:id,user_id,profile_image,bio,cover_page_image',
            'ratings:id,rateable_id,rating,comment'
        ])
            ->nearest($latitude, $longitude)
            ->paginate(10);

        // attach average rating
        $veterinaryClinics->transform(function ($veterinaryClinic) {
            $veterinaryClinic->average_rating = $veterinaryClinic->averageRating();
            return $veterinaryClinic;
        });

        return response()->json(['message' => 'success', 'veterinary_clinics' => $veterinaryClinics], 200);
    }

    // get veterinary clinic by id
    public function getVeterinaryClinicById($id)
    {
        $user = auth()->user();

        $clinic = VeterinaryClinic::with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,bio,profile_image,cover_page_image', 'user.media:id,user_id,file_path'])
            ->select('id', 'user_id', 'name_of_clinic', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'view', 'availability', 'is_approved')
            ->where('id', $id)
            ->first();

        if (!$clinic) {
            return response()->json(['message' => 'Veterinary clinic not found.'], 404);
        }

        // check if user is admin by email
        $isAdmin = $user && Admin::where('email', $user->email)->exists();

        // increment views if not self and not admin
        if ($user && (int) $user->id !== (int) $clinic->user_id && !$isAdmin) {
            $clinic->recordView();
        }


        $clinic->average_rating = $clinic->averageRating();


        return response()->json(['clinic' => $clinic], 200);
    }

    public function getMyVeterinaryProfile()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $clinic = VeterinaryClinic::where('user_id', $user->id)
            ->with(['ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,bio,profile_image,cover_page_image'])
            ->first();

        if (!$clinic) {
            return response()->json(['message' => 'No veterinary clinic profile found.'], 404);
        }

        $clinic->average_rating = $clinic->averageRating();

        return response()->json(['message' => "success", 'veterinary_clinic' => $clinic], 200);
    }

    public function verify($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $clinic = VeterinaryClinic::findOrFail($id);
        $user = User::find($clinic->user_id);

        if ($user) {
            $clinic->is_approved = 1;
            $clinic->save();
            // Notify the user
            $user->sendNotification(
                'veterinary_clinic_verified',
                'Verified Clinic',
                "{$user->first_name} Your clinic have been verified.",
                ['veterinary_clinic_id' => $clinic->id, 'user_id' => $user->id]
            );

            $user->notify(new \App\Notifications\AdminActionNotification(
                'License Number Verified',
                'Congratulations ' . $user->first_name . ', We are so pleased to inform you that your Clinic License Number has been verified and your account is now visible on our platform.'
            ));
        }

        // Log admin activity
        $this->createAdminActivity(
            $admin->id,
            'veterinary_clinic_verified',
            'Verified Clinic',
            "Admin {$admin->name} verified {$user->first_name} clinic (ID: {$clinic->id}).",
            ['clinic_id' => $clinic->id, 'user_id' => $clinic->user_id, 'staff_id' => $admin->staff_id]
        );

        return response()->json(['message' => 'Veterinary Clinic Verified'], 200);
    }
}
