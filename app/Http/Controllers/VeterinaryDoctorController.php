<?php

namespace App\Http\Controllers;

use App\Models\VeterinaryDoctor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VeterinaryDoctorController extends Controller
{
    //
    public function addVeterinaryDoctor(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // validate request
        $validated = $request->validate([
            'user_id' => 'required|integer|unique:veterinary_doctor,user_id',
            'practice_license_num' => 'required|string|max:255',
            'specialty' => 'required|string|max:255',
            'list_them' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'longitude' => 'required|numeric',
            'latitude' => 'required|numeric',
        ]);

        // create veterinary doctor
        $veterinaryDoctor = VeterinaryDoctor::create([
            'user_id' => $validated['user_id'],
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

        // return
        return response()->json(['message' => 'Veterinary clinic added successfully. Pending approval.', 'veterinary_doctor' => $veterinaryDoctor], 200);
    }

    // get all veterinary doctors
    public function getAllVeterinaryDoctors()
    {
        $veterinaryDoctors = VeterinaryDoctor::with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rated_user_id,rating,comment'])
            ->select('id', 'user_id', 'specialty', 'list_them', 'address', 'longitude', 'latitude', 'role', 'availability', 'created_at')
            ->paginate(10);

            // add average rating to each veterinary doctor
            $veterinaryDoctors->getCollection()->transform(function ($doctor){
                $doctor->average_rating = $doctor->ratings->avg('rating');
                return $doctor;
            });

        return response()->json(['veterinary_doctors' => $veterinaryDoctors], 200);
    }

    // get veterinary doctor base on search query
    public function searchVeterinaryDoctors(Request $request)
    {
        $query = $request->input('query');
        if (!$query) {
            return response()->json(['message' => 'Query parameter is required.'], 400);
        }

        $veterinaryDoctors = VeterinaryDoctor::where('specialty', 'like', "%$query%")
            ->orWhere('list_them', 'like', "%$query%")
            ->orWhere('address', 'like', "%$query%")
            ->with(['user:id,email,first_name,last_name,phone_num,state,country','ratings:id,rated_user_id,rating,comment'])
            ->select('id', 'user_id', 'specialty', 'list_them', 'address', 'longitude', 'latitude', 'role', 'availability')
            ->paginate(10);

            // add average rating to each veterinary doctor
            $veterinaryDoctors->getCollection()->transform(function ($doctor){
                $doctor->average_rating = $doctor->ratings->avg('rating');
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
            ->leftJoin('rating', 'veterinary_doctor.user_id', '=', 'rating.rated_user_id')
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
    public function getVeterinaryDoctorById($id){
        $veterinaryDoctor = VeterinaryDoctor::with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rated_user_id,rating,comment'])
            ->select('id', 'user_id', 'specialty', 'list_them', 'address', 'availability', 'role', 'created_at')
            ->where('id', $id)
            ->first();

            // get average rating 
            if($veterinaryDoctor){
                $veterinaryDoctor->average_rating = $veterinaryDoctor->ratings->avg('rating');
            }else{
                 return response()->json(['message' => 'Veterinary doctor not found.'], 404);
            }

            // return veterinary doctor
            return response()->json(['veterinary_doctor' => $veterinaryDoctor], 200);
    }


}
