<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Role;
use App\Models\User;
use App\Models\VeterinaryParaprofessional;
use App\Traits\BroadcastsAdminEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VeterinaryParaprofessionalController extends Controller
{
    use BroadcastsAdminEvents;
    //
    public function addVeterinaryParaprofessional(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // validate request
        $validated = $request->validate([
            'name_of_institution' => 'required|string|max:255',
            'graduation_year' => 'required|string|max:30',
            'expected_year_of_graduation' => 'sometimes|nullable|string|max:30',
            'specialty' => 'required|string|max:255',
            'list_them' => 'required|string|max:255',
            'contact_num' => 'required|string|max:15',
            'address' => 'required|string|max:500',
            'longitude' => 'required|numeric',
            'latitude' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            // create veterinary paraprofessional
            $veterinaryParaprofessional = VeterinaryParaprofessional::create([
                'user_id' => $user->id,
                'name_of_institution' => $validated['name_of_institution'],
                'graduation_year' => $validated['graduation_year'],
                'expected_year_of_graduation' => $validated['expected_year_of_graduation'] ?? null,
                'specialty' => $validated['specialty'],
                'list_them' => $validated['list_them'],
                'contact_num' => $validated['contact_num'],
                'address' => $validated['address'],
                'longitude' => $validated['longitude'],
                'latitude' => $validated['latitude'],
                'role' => 'Veterinary Paraprofessional',
                'is_approved' => 0, // default to pending
                'created_at' => Carbon::now(),
            ]);

            $activeRole = Role::where('name', 'veterinary_paraprofessional')->first();
            if (!$activeRole) {
                return response()->json(['message' => 'Role not found.'], 404);
            }
            $user->roles()->attach($activeRole->id);
            $user->update([
                'active_role_id' => $activeRole->id,
            ]);

            DB::commit();
            $user->logActivity(
                action: 'create_veterinary_paraprofessional',
                title: 'Created Veterinary Paraprofessional Profile',
                detail: "User {$user->first_name} {$user->last_name} created a veterinary paraprofessional profile",
                meta: ['veterinary_paraprofessional_id' => $veterinaryParaprofessional->id]
            );

            $admins = Admin::all();
            foreach ($admins as $admin) {
                $this->createAdminNotification(
                    $admin->id,
                    "New Veterinary Paraprofessional Pending Approval",
                    "vet_profile",
                    "A new veterinary paraprofessional profile has been created by {$user->first_name} {$user->last_name}.",
                    ['paraprofessional_id' => $veterinaryParaprofessional->id, 'user_id' => $user->id, 'staff_id' => $admin->staff_id]
                );
                $this->createAdminActivity(
                    $admin->id,
                    "vet_profile_review",
                    "New Vet Paraprofessional Created",
                    "Review vet paraprofessional {$veterinaryParaprofessional->id} created by user {$user->name}",
                    ['paraprofessional_id' => $veterinaryParaprofessional->id, 'staff_id' => $admin->staff_id]
                );
            }

            // return
            return response()->json(['message' => 'Veterinary paraprofessional added successfully. Pending', 'veterinary_paraprofessional' => $veterinaryParaprofessional], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    // get all veterinary paraprofessionals
    public function getAllVeterinaryParaprofessionals()
    {
        $veterinaryParaprofessionals = VeterinaryParaprofessional::with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->where('is_approved', '!=', 0)
            ->select('id', 'user_id', 'name_of_institution', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability', 'created_at')
            ->paginate(10);

        // add average rating to each veterinary paraprofessional
        $veterinaryParaprofessionals->getCollection()->transform(function ($veterinaryParaprofessional) {
            $veterinaryParaprofessional->average_rating = $veterinaryParaprofessional->averageRating();
            return $veterinaryParaprofessional;
        });

        return response()->json(['veterinary_paraprofessionals' => $veterinaryParaprofessionals], 200);
    }

    // get veterinary paraprofessional base on search query
    public function searchVeterinaryParaprofessionals(Request $request)
    {
        $query = $request->input('query');

        $veterinaryParaprofessionals = VeterinaryParaprofessional::with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->where('is_approved', '!=', 0)
            ->when($query, function ($q) use ($query) {
                $q->where('specialty', 'like', "%$query%")
                    ->orWhere('name_of_institution', 'like', "%$query%")
                    ->orWhere('list_them', 'like', "%$query%")
                    ->orWhere('address', 'like', "%$query%");
            })
            ->select('id', 'user_id', 'name_of_institution', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability')
            ->paginate(10);

        // add average rating to each veterinary paraprofessional
        $veterinaryParaprofessionals->getCollection()->transform(function ($veterinaryParaprofessional) {
            $veterinaryParaprofessional->average_rating = $veterinaryParaprofessional->averageRating();
            return $veterinaryParaprofessional;
        });

        return response()->json(['veterinary_paraprofessionals' => $veterinaryParaprofessionals], 200);
    }

    // get veterinary paraprofessional by longitude and latitude
    public function getVeterinaryParaprofessionalsByLocation(Request $request)
    {
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        if (!$latitude || !$longitude) {
            return response()->json(['message' => 'Latitude and Longitude parameters are required.'], 400);
        }

        // Haversine formula to calculate distance
        $haversine = "(6371 * acos(cos(radians($latitude)) * cos(radians(veterinary_paraprofessional.latitude)) * cos(radians(veterinary_paraprofessional.longitude) - radians($longitude)) + sin(radians($latitude)) * sin(radians(veterinary_paraprofessional.latitude))))";

        $veterinaryParaprofessionals = VeterinaryParaprofessional::join('users_registration', 'veterinary_paraprofessional.user_id', '=', 'users_registration.id')
            ->leftJoin('rating', 'veterinary_paraprofessional.user_id', '=', 'rating.rateable_id')
            ->leftJoin('profile', 'veterinary_paraprofessional.user_id', '=', 'profile.user_id')
            ->select(
                'veterinary_paraprofessional.id',
                'veterinary_paraprofessional.user_id',
                'veterinary_paraprofessional.name_of_institution',
                'veterinary_paraprofessional.specialty',
                'veterinary_paraprofessional.list_them',
                'veterinary_paraprofessional.contact_num',
                'veterinary_paraprofessional.address',
                'veterinary_paraprofessional.longitude',
                'veterinary_paraprofessional.latitude',
                'veterinary_paraprofessional.role',
                'veterinary_paraprofessional.availability',
                'users_registration.email',
                'users_registration.first_name',
                'users_registration.last_name',
                'users_registration.phone_num',
                'users_registration.state',
                'users_registration.country',
                'profile.profile_image',
                'profile.id',
                'profile.bio',
                'profile.cover_page_image',
                DB::raw('AVG(rating.rating) as average_rating'),
                DB::raw("$haversine AS distance")
            )
            ->whereNotNull('veterinary_paraprofessional.latitude')
            ->whereNotNull('veterinary_paraprofessional.longitude')
            ->orderBy('distance', 'asc')
            ->limit(10)
            ->get();

        return response()->json(['veterinary_paraprofessionals' => $veterinaryParaprofessionals], 200);
    }

    // get veterinary paraprofessional by id
    public function getVeterinaryParaprofessionalById($id)
    {
        $user = auth()->user();

        $veterinaryParaprofessional = VeterinaryParaprofessional::with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,bio,profile_image,cover_page_image'])
            ->select('id', 'user_id', 'name_of_institution', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'view', 'availability')
            ->where('id', $id)
            ->first();

        if (!$veterinaryParaprofessional) {
            return response()->json(['message' => 'Veterinary paraprofessional not found'], 404);
        }

        // check if user is admin by email
        $isAdmin = $user && Admin::where('email', $user->email)->exists();

        // increment views if not self and not admin
        if ($user && $user->id !== $veterinaryParaprofessional->user_id && !$isAdmin) {
            $veterinaryParaprofessional->recordView();
        }

        $veterinaryParaprofessional->average_rating = $veterinaryParaprofessional->averageRating();


        // return veterinary paraprofessional
        return response()->json(['veterinary_paraprofessional' => $veterinaryParaprofessional], 200);
    }

    public function verify($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $paraprofessional = VeterinaryParaprofessional::findOrFail($id);
        $user = User::find($paraprofessional->user_id);

        if ($user) {
            $paraprofessional->is_approved = 1;
            $paraprofessional->save();
            // Notify the user
            $user->sendNotification(
                'veterinary_paraprofessional_verified',
                'Verified Veterinary Paraprofessional',
                "{$user->first_name} You have been verified.",
                ['veterinary_paraprofessional_id' => $paraprofessional->id, 'user_id' => $user->id]
            );

            // Log user activity
            $user->logActivity(
                'veterinary_paraprofessional_verified',
                'Verified Veterinary paraprofessional',
                "{$user->first_name} Your verification was approved by admin.",
                ['paraprofessional_id' => $paraprofessional->id]
            );

            $user->notify(new \App\Notifications\AdminActionNotification(
                'Verified',
                'Congratulations ' . $user->first_name . ', We are so pleased to inform you that your Details has been verified and your account is now visible on our platform.'
            ));
        }

        // Log admin activity
        $this->createAdminActivity(
            $admin->id,
            'veterinary_paraprofessional_verified',
            'Verified Veterinary Paraprofessional',
            "Admin {$admin->name} verified {$user->first_name} (ID: {$paraprofessional->id}).",
            ['paraprofessional_id' => $paraprofessional->id, 'user_id' => $paraprofessional->user_id, 'staff_id' => $admin->staff_id]
        );

        return response()->json(['message' => 'Veterinary Paraprofessional Verified']);
    }
}
