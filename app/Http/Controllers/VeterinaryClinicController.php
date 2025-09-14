<?php

namespace App\Http\Controllers;

use App\Models\VeterinaryClinic;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VeterinaryClinicController extends Controller
{
    //
    public function addVeterinaryClinic(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
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

        return response()->json(['message' => 'Veterinary clinic added successfully. Pending approval.', 'clinic' => $clinic], 200);
    }

    // get all veterinary clinics
    public function getAllVeterinaryClinics()
    {
        $clinics = VeterinaryClinic::with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'name_of_clinic', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability', 'created_at')
            ->inRandomOrder()
            ->paginate(10);

        // add average rating to each veterinary clinic
        $clinics->getCollection()->transform(function ($clinic) {
            $clinic->average_rating = $clinic->averageRating();
            return $clinic;
        });

        return response()->json(['clinics' => $clinics], 200);
    }

    // get veterinary clinics base on search query
    public function searchVeterinaryClinics(Request $request)
    {
        $query = $request->input('query', '');

        if (!$query) {
            return response()->json(['clinics' => []], 200);
        }

        $clinics = VeterinaryClinic::where('name_of_clinic', 'like', "%$query%")
            ->orWhere('specialty', 'like', "%$query%")
            ->orWhere('list_them', 'like', "%$query%")
            ->orWhere('address', 'like', "%$query%")
            ->with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'name_of_clinic', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability')
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
        $radius = $request->input('radius', 10); // default to 10 km if not provided

        if (!$latitude || !$longitude) {
            return response()->json(['message' => 'Latitude and Longitude are required.'], 400);
        }

        // Haversine formula to calculate distance
        $haversine = "(6371 * acos(cos(radians($latitude)) * cos(radians(veterinary_clinic.latitude)) * cos(radians(veterinary_clinic.longitude) - radians($longitude)) + sin(radians($latitude)) * sin(radians(veterinary_clinic.latitude))))";

        $veterinaryClinics = VeterinaryClinic::join('users_registration', 'veterinary_clinic.user_id', '=', 'users_registration.id')
            ->leftJoin('rating', 'veterinary_clinic.user_id', '=', 'rating.rateable_id')
            ->leftJoin('profile', 'veterinary_clinic.user_id', '=', 'profile.user_id')
            ->select(
                'veterinary_clinic.id',
                'veterinary_clinic.user_id',
                'veterinary_clinic.name_of_clinic',
                'veterinary_clinic.specialty',
                'veterinary_clinic.list_them',
                'veterinary_clinic.contact_num',
                'veterinary_clinic.address',
                'veterinary_clinic.longitude',
                'veterinary_clinic.latitude',
                'veterinary_clinic.role',
                'veterinary_clinic.availability',
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
            ->whereNotNull('veterinary_clinic.latitude')
            ->whereNotNull('veterinary_clinic.longitude')
            ->having('distance', '<=', $radius)
            ->orderBy('distance', 'asc')
            ->paginate(10);

        if ($veterinaryClinics === 0) {
            return response()->json(['message' => 'No veterinary clinics found within the specified radius.'], 200);
        }

        return response()->json(['veterinary_clinics' => $veterinaryClinics], 200);
    }

    // get veterinary clinic by id
    public function getVeterinaryClinicById($id)
    {
        $clinic = VeterinaryClinic::with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,bio,profile_image,cover_page_image'])
            ->select('id', 'user_id', 'name_of_clinic', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability')
            ->where('id', $id)
            ->first();

        if ($clinic) {
            $clinic->average_rating = $clinic->averageRating();
        } else {
            return response()->json(['message' => 'Veterinary clinic not found.'], 404);
        }

        return response()->json(['clinic' => $clinic], 200);
    }
}
