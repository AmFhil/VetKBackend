<?php

namespace App\Http\Controllers;

use App\Models\VeterinaryParaprofessional;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VeterinaryParaprofessionalController extends Controller
{
    //
    public function addVeterinaryParaprofessional(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // validate request
        $validated = $request->validate([
            'user_id' => 'required|integer|unique:veterinary_paraprofessional,user_id',
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

        // create veterinary paraprofessional
        $veterinaryParaprofessional = VeterinaryParaprofessional::create([
            'user_id' => $validated['user_id'],
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

        // return
        return response()->json(['message' => 'Veterinary paraprofessional added successfully. Pending', 'veterinary_paraprofessional' => $veterinaryParaprofessional], 200);
    }

    // get all veterinary paraprofessionals
   public function getAllVeterinaryParaprofessionals()
    {
        $veterinaryParaprofessionals = VeterinaryParaprofessional::with(['user:id,email,first_name,last_name,phone_num,state,country','ratings:id,rated_user_id,rating,comment'])
            ->select('id', 'user_id', 'name_of_institution', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability', 'created_at')
            ->paginate(10);

            // add average rating to each veterinary paraprofessional
            $veterinaryParaprofessionals->getCollection()->transform(function ($veterinaryParaprofessional){
                $veterinaryParaprofessional->average_rating = $veterinaryParaprofessional->averageRating();
                return $veterinaryParaprofessional;
            });

        return response()->json(['veterinary_paraprofessionals' => $veterinaryParaprofessionals], 200);
    }

    // get veterinary paraprofessional base on search query
    public function searchVeterinaryParaprofessionals(Request $request)
    {
        $query = $request->input('query');
        if (!$query) {
            return response()->json(['message' => 'Query parameter is required.'], 400);
        }

        $veterinaryParaprofessionals = VeterinaryParaprofessional::where('specialty', 'like', "%$query%")
            ->orWhere('list_them', 'like', "%$query%")
            ->orWhere('address', 'like', "%$query%")
            ->with(['user:id,email,first_name,last_name,phone_num,state,country','ratings:id,rated_user_id,rating,comment'])
            ->select('id', 'user_id', 'name_of_institution', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability')
            ->paginate(10);

            // add average rating to each veterinary paraprofessional
            $veterinaryParaprofessionals->getCollection()->transform(function ($veterinaryParaprofessional){
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
        $veterinaryParaprofessional = VeterinaryParaprofessional::with(['user:id,email,first_name,last_name,phone_num,state,country','ratings:id,rated_user_id,rating,comment'])
            ->select('id','user_id','name_of_institution','specialty','list_them','contact_num','address','longitude','latitude','role','availability')
            ->where('id', $id)
            ->first();

            // get average rating
            if($veterinaryParaprofessional){
                $veterinaryParaprofessional->average_rating = $veterinaryParaprofessional->averageRating();
            }else{
                 return response()->json(['message' => 'Veterinary paraprofessional not found.'], 404);
            }

            // return veterinary paraprofessional
            return response()->json(['veterinary_paraprofessional' => $veterinaryParaprofessional], 200);
    }
}
