<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\UserActivity;
use App\Models\VeterinaryDoctor;
use App\Traits\BroadcastsAdminEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VeterinaryDoctorController extends Controller
{
    use BroadcastsAdminEvents;
    //
    public function addVeterinaryDoctor(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // validate request
        $validated = $request->validate([
            'practice_license_num' => 'required|string|max:255',
            'specialty' => 'required|string|max:255',
            'list_them' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'longitude' => 'required|numeric',
            'latitude' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            // create veterinary doctor
            $veterinaryDoctor = VeterinaryDoctor::create([
                'user_id' => $user->id,
                'practice_license_num' => $validated['practice_license_num'],
                'specialty' => $validated['specialty'],
                'list_them' => $validated['list_them'],
                'address' => $validated['address'],
                'longitude' => $validated['longitude'],
                'latitude' => $validated['latitude'],
                'role' => 'Veterinarian',
                'is_approved' => 0, // default to pending
                'created_at' => Carbon::now(),
            ]);

            $user->update([
                'active_role_id' => $veterinaryDoctor->id,
            ]);

            DB::commit();
            // log activity via private helper
            $user->logActivity(
                action: 'create_veterinary_doctor',
                title: 'Created Veterinary Doctor Profile',
                detail: "User {$user->first_name} {$user->last_name} created a veterinary doctor profile",
                meta: ['veterinary_doctor_id' => $veterinaryDoctor->id]
            );

            // notify admins (optional)
            // $admins = Admin::all();
            // foreach ($admins as $admin) {
            //     $this->createAdminNotification(
            //         $admin->id,
            //         "New Veterinary Doctor Pending Approval",
            //         "vet_profile",
            //         "A new veterinary doctor profile has been created by {$user->first_name} {$user->last_name}.",
            //         ['doctor_id' => $veterinaryDoctor->id, 'user_id' => $user->id]
            //     );
            // $this->createAdminActivity(
            //     $admin->id,
            //     "vet_profile_review",
            //     "New Vet Doctor Created",
            //     "Review vet doctor {$veterinaryDoctor->id} created by user {$user->id}",
            //     ['doctor_id' => $veterinaryDoctor->id]
            // );
            // }

            // return
            return response()->json(['message' => 'Veterinary doctor added successfully. Pending approval.', 'veterinary_doctor' => $veterinaryDoctor], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    // get all veterinary doctors
    public function getAllVeterinaryDoctors()
    {
        $veterinaryDoctors = VeterinaryDoctor::with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'specialty', 'list_them', 'address', 'longitude', 'latitude', 'role', 'availability', 'created_at')
            ->paginate(10);

        // add average rating to each veterinary doctor
        $veterinaryDoctors->getCollection()->transform(function ($doctor) {
            $doctor->average_rating = $doctor->averageRating();
            return $doctor;
        });

        return response()->json(['veterinary_doctors' => $veterinaryDoctors], 200);
    }

    // get veterinary doctor base on search query
    public function searchVeterinaryDoctors(Request $request)
    {
        $query = $request->input('query');
        if (!$query) {
            return response()->json(['veterinary_doctors' => []], 200);
        }

        $veterinaryDoctors = VeterinaryDoctor::where('specialty', 'like', "%$query%")
            ->orWhere('list_them', 'like', "%$query%")
            ->orWhere('address', 'like', "%$query%")
            ->with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'specialty', 'list_them', 'address', 'longitude', 'latitude', 'role', 'availability')
            ->paginate(10);

        // add average rating to each veterinary doctor
        $veterinaryDoctors->getCollection()->transform(function ($doctor) {
            $doctor->average_rating = $doctor->averageRating();
            return $doctor;
        });

        return response()->json(['veterinary_doctors' => $veterinaryDoctors], 200);
    }

    // get veterinary doctor by longitude and latitude
    public function getVeterinaryDoctorsByLocation(Request $request)
    {
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        if (!$latitude || !$longitude) {
            return response()->json(['message' => 'Latitude and Longitude are required.'], 400);
        }

        // Haversine formula to calculate distance
        $haversine = "(6371 * acos(cos(radians($latitude)) * cos(radians(veterinary_doctor.latitude)) * cos(radians(veterinary_doctor.longitude) - radians($longitude)) + sin(radians($latitude)) * sin(radians(veterinary_doctor.latitude))))";

        $veterinaryDoctors = DB::table('veterinary_doctor')
            ->join('users_registration', 'veterinary_doctor.user_id', '=', 'users_registration.id')
            ->leftJoin('rating', 'veterinary_doctor.user_id', '=', 'rating.rateable_id')
            ->leftJoin('profile', 'veterinary_doctor.user_id', '=', 'profile.user_id')
            ->select(
                'veterinary_doctor.id',
                'veterinary_doctor.user_id',
                'veterinary_doctor.specialty',
                'veterinary_doctor.list_them',
                'veterinary_doctor.address',
                'veterinary_doctor.longitude',
                'veterinary_doctor.latitude',
                'veterinary_doctor.role',
                'veterinary_doctor.availability',
                'users_registration.email',
                'users_registration.first_name',
                'users_registration.last_name',
                'users_registration.phone_num',
                'users_registration.state',
                'users_registration.country',
                'profile.profile_image',
                'profile.bio',
                'profile.id',
                'profile.cover_page_image',
                DB::raw('AVG(rating.rating) as average_rating'),
                DB::raw("$haversine AS distance")
            )
            ->whereNotNull('veterinary_doctor.latitude')
            ->whereNotNull('veterinary_doctor.longitude')
            ->orderBy('distance', 'asc')
            ->limit(10)
            ->get();

        return response()->json(['veterinary_doctors' => $veterinaryDoctors], 200);
    }

    // get veterinary doctor by id
    // get veterinary doctor by id
    public function getVeterinaryDoctorById($id)
    {
        $user = auth()->user();

        $veterinaryDoctor = VeterinaryDoctor::with([
            'user:id,email,first_name,last_name,phone_num,state,country',
            'ratings:id,rateable_id,rating,comment',
            'user.profile:id,user_id,bio,profile_image,cover_page_image'
        ])
            ->select('id', 'user_id', 'specialty', 'list_them', 'address', 'availability', 'role', 'view', 'created_at')
            ->where('id', $id)
            ->first();

        if (!$veterinaryDoctor) {
            return response()->json(['message' => 'Veterinary doctor not found.'], 404);
        }

        // check if user is admin by email
        $isAdmin = $user && Admin::where('email', $user->email)->exists();

        // increment views if not self and not admin
        if ($user && $user->id !== $veterinaryDoctor->user_id && !$isAdmin) {
            $veterinaryDoctor->recordView();
        }

        $veterinaryDoctor->average_rating = $veterinaryDoctor->averageRating();

        return response()->json(['veterinary_doctor' => $veterinaryDoctor], 200);
    }

    public function getMyVeterinaryProfile()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $veterinaryDoctor = VeterinaryDoctor::where('user_id', $user->id)
            ->with(['ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,bio,profile_image,cover_page_image'])
            ->first();

        if (!$veterinaryDoctor) {
            return response()->json(['message' => 'No veterinary profile found.'], 404);
        }

        $veterinaryDoctor->average_rating = $veterinaryDoctor->averageRating();

        return response()->json(['veterinary_doctor' => $veterinaryDoctor], 200);
    }
}
