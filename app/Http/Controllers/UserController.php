<?php

namespace App\Http\Controllers;

use App\Models\Profiles;
use App\Models\Role;
use App\Models\VeterinaryClinic;
use App\Models\VeterinaryDoctor;
use App\Models\VeterinaryParaprofessional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    //feed calculator
    public function feedCalculator(Request $request)
    {
        $livestock_category = $request->livestock_category;
        switch ($livestock_category) {
            case 'Poultry':
                $bird_type = $request->bird_type;
                $feed_type = $request->feed_type;
                if ($bird_type == 'Broilers') {
                    return $this->PoultryFeedType($feed_type, $request);
                } else {
                    return $this->PoultryFeedType($feed_type, $request);
                }
                break;
            case 'Fish':
                $fish = [
                    ['pellet_size' => 2, 'feed' => ($request->no_of_fish * 0.015), 'space_requires' => ($request->no_of_fish / 8)],
                    ['pellet_size' => 3, 'feed' => ($request->no_of_fish * 0.03), 'space_requires' => ''],
                    ['pellet_size' => 4.5, 'feed' => ($request->no_of_fish * 0.15), 'space_requires' => ''],
                    ['pellet_size' => 6, 'feed' => ($request->no_of_fish * 0.24), 'space_requires' => ''],
                    ['pellet_size' => 8, 'feed' => ($request->no_of_fish * 0.455), 'space_requires' => '']
                ];
                return $fish;
                break;


            default:
                $pig = [
                    ['age' => 2, 'feed' => ($request->no_of_fish * 1.7), 'space_requires' => ($request->no_of_fish / 8)],
                    ['pellet_size' => 3, 'feed' => ($request->no_of_pig * 1.7), 'space_requires' => ($request->no_of_pig / 0.6)],
                    ['pellet_size' => 4, 'feed' => ($request->no_of_pig * 1.8), 'space_requires' => ($request->no_of_pig / 1.6)],
                    ['pellet_size' => 5, 'feed' => ($request->no_of_pig * 2.0), 'space_requires' => ($request->no_of_pig / 7)],
                    ['pellet_size' => 6, 'feed' => ($request->no_of_pig * 2.0), 'space_requires' => '-'],
                    ['pellet_size' => 7, 'feed' => ($request->no_of_pig * 2.3), 'space_requires' => '-'],
                    ['pellet_size' => 7, 'feed' => ($request->no_of_pig * 2.3), 'space_requires' => '-']
                ];
                return $pig;
                break;
        }
    }

    //reusable function for feedcalculator
    public function PoultryFeedType($feed_type, $request)
    {
        $no_of_week = $request->no_of_week;
        $no_of_bird = $request->no_of_bird;
        switch ($feed_type) {
            case 'Starter':
                $feed = $no_of_week * $no_of_bird;
                $feeder = floor((($no_of_bird - 1) / 20) + 1);
                $chicks = 0.09 * $no_of_bird;
                $birds = $no_of_bird / 5;
                return [
                    'livestock_category' => $request->livestock_category,
                    'bird_type' => $request->bird_type,
                    'space' => "Space requirement for Chicks - " . $chicks . "Sqm | " . $birds . "Sqm for Birds",
                    'feed_type' => $request->feed_type,
                    'no_of_bird' => $no_of_bird,
                    'feeder' => $feeder,
                    'drinker' => $feeder,
                    'weight' => $feed . 'kg'
                ];
                break;
            case 'Finisher':
                $feeder = floor((($no_of_bird - 1) / 15) + 1);
                $feed = $no_of_week * $no_of_bird;
                $birds = $no_of_bird / 4;
                return [
                    'livestock_category' => $request->livestock_category,
                    'bird_type' => $request->bird_type,
                    'space' => "Space requirement for Birds - " . $birds . "Sqm for Birds",
                    'feed_type' => $request->feed_type,
                    'no_of_bird' => $no_of_bird,
                    'feeder' => $feeder,
                    'drinker' => $feeder,
                    'weight' => $feed . 'kg'
                ];
                break;
            case 'Chick Mash':
                $feeder = floor((($no_of_bird - 1) / 20) + 1);
                $feed = 1.8 * $no_of_bird;
                return [
                    'livestock_category' => $request->livestock_category,
                    'bird_type' => $request->bird_type,
                    'feed_type' => $request->feed_type,
                    'no_of_bird' => $no_of_bird,
                    'feeder' => $feeder,
                    'drinker' => $feeder,
                    'weight' => $feed . 'kg'
                ];
                break;
            case 'Growers Mash':
                $feeder = floor((($no_of_bird - 1) / 15) + 1);
                $feed = 6.1 * $no_of_bird;
                return [
                    'livestock_category' => $request->livestock_category,
                    'bird_type' => $request->bird_type,
                    'feed_type' => $request->feed_type,
                    'no_of_bird' => $no_of_bird,
                    'feeder' => $feeder,
                    'drinker' => $feeder,
                    'weight' => $feed . 'kg'
                ];
                break;
            case 'Layer Mash/Pellet':
                $feeder = floor((($no_of_bird - 1) / 15) + 1);
                $feed = 8.1 * $no_of_bird;
                return [
                    'livestock_category' => $request->livestock_category,
                    'bird_type' => $request->bird_type,
                    'feed_type' => $request->feed_type,
                    'no_of_bird' => $no_of_bird,
                    'feeder' => $feeder,
                    'drinker' => $feeder,
                    'weight' => $feed . 'kg'
                ];
                break;
            default:
                break;
        }
    }

    //disease predictor
    public function diseasePredictor(Request $request)
    {
        $counter = 0;
        $livestock_category = strtolower($request->livestock_category) . '_disease';
        $diseaseSentence = '';
        for ($l = 0; $l < count($request->diseases); $l++) {
            $diseaseSentence = $diseaseSentence . ', ' . $request->diseases[$l];
        }
        for ($k = 0; $k < count($request->diseases); $k++) {
            for ($i = 0; $i < count($this->$livestock_category); $i++) {
                for ($j = 0; $j < count($this->$livestock_category[$i]['array']); $j++) {
                    for ($l = 0; $l < count($this->$livestock_category[$i]['array'][$j]['diseases']); $l++) {
                        if ($j == $j && in_array($request->diseases[$k], $this->$livestock_category[$i]['array'][$j]['diseases'])) {
                            $counter = $counter + 1;
                        }
                        if (($counter)) {
                            return 'Based on the signs and symptoms you have selected, the suspected condition is <p style="color:red">' . $this->$livestock_category[$i]['array'][$j]['disease_name'];
                        }
                    }
                }
                if (($i == count($this->$livestock_category) - 1) && ($counter == 0)) {
                    return 'These Symptoms do not match any disease yet on our System!';
                }
            }
        }
    }

    //All livestock diseases based on category
    public function getLivestockDiseases(Request $request)
    {
        $livestock_category = strtolower($request->livestock);
        return $this->$livestock_category;
    }

    // use long and lati to get nearest veterinary doctor
    public function getNearestVeterinaryDoctor(Request $request)
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
            ->limit(5)
            ->get();

        return response()->json(['veterinary_doctors' => $veterinaryDoctors], 200);
    }

    // get nearest location by search
    public function searchLocation(Request $request)
    {
        $query = $request->input('query');

        if (!$query) {
            return response()->json(['veterinary_doctors' => []], 200);
        }

        $veterinaryDoctors = VeterinaryDoctor::where('address', 'like', "%$query%")
            ->with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment'])
            ->select('id', 'user_id', 'specialty', 'list_them', 'address', 'longitude', 'latitude', 'role', 'availability')
            ->limit(5);

        // add average rating to each veterinary doctor
        $veterinaryDoctors->getCollection()->transform(function ($doctor) {
            $doctor->average_rating = $doctor->averageRating();
            return $doctor;
        });

        return response()->json(['veterinary_doctors' => $veterinaryDoctors], 200);
    }

    //update profile
    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 403);
        }

        $request->validate([
            'email' => 'sometimes|email|unique:users_registration,email,' . $user->id,
            'current_password' => 'sometimes|string',
            'password' => 'sometimes|string|min:8|confirmed',
            'first_name' => 'sometimes|string|max:50|min:3',
            'last_name' => 'sometimes|string|max:50|min:3',
            'phone_num' => 'sometimes|string|max:15',
            'contact_num' => 'sometimes|string|max:15',
            'address' => 'sometimes|string|max:500',
            'practice_license_num' => 'sometimes|string|max:255',
            'clinic_name' => 'sometimes|string|max:100',
            'name_of_institution' => 'sometimes|string|max:255',
            'graduation_year' => 'sometimes|string|max:30',
            'expected_year_of_graduation' => 'sometimes|nullable|string|max:30',
            'specialty' => 'sometimes|string|max:255',
            'list_them' => 'sometimes|string|max:255',
            'longitude' => 'sometimes|numeric',
            'latitude' => 'sometimes|numeric',
            'status' => 'sometimes|string|in:vet,vet_clinic,vet_paraprofessional',
            'bio' => 'sometimes|string|max:150',
            'profile_image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:1048',
            'cover_page_image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:1048'
        ]);

        //  Handle password update (bcrypt + sha1 upgrade)
        if ($request->filled('current_password') && $request->filled('password')) {
            $currentPassword = $request->current_password;

            $passwordMatches = false;

            if (!is_null($user->provider)) {
                return response()->json(['message' => 'You cannot change password for social login accounts'], 422);
            }

            // check bcrypt
            if (Hash::check($currentPassword, $user->password)) {
                $passwordMatches = true;
            }
            // check sha1
            elseif ($user->password === sha1($currentPassword)) {
                $passwordMatches = true;

                // upgrade sha1 to bcrypt
                $user->password = Hash::make($currentPassword);
                $user->password_algorithm = 'bcrypt';
                $user->save();
            }

            if (!$passwordMatches) {
                return response()->json(['message' => 'Current password is incorrect'], 422);
            }

            // set new password
            $user->password = Hash::make($request->password);
            $user->password_algorithm = 'bcrypt';
        }

        //  Update email only if provider == null
        if ($request->filled('email')) {
            if (!is_null($user->provider)) {
                return response()->json(['message' => 'You cannot change email for social login accounts'], 422);
            }
            $user->email = $request->email;
        }

        //  Update base user info
        $user->fill($request->only(['first_name', 'last_name', 'phone_num']));
        $user->save();

        //  Profile table (bio, images)
        $profile = Profiles::firstOrNew(['user_id' => $user->id]);

        if ($request->filled('bio')) {
            $profile->bio = $request->bio;
        }

        if ($request->hasFile('profile_image')) {
            // delete old profile image if exists
            if ($profile->profile_image && Storage::disk('public')->exists($profile->profile_image)) {
                Storage::disk('public')->delete($profile->profile_image);
            }
            $profile->profile_image = $request->file('profile_image')->store('profiles', 'public');
        }

        if ($request->hasFile('cover_page_image')) {
            // delete old cover image if exists
            if ($profile->cover_page_image && Storage::disk('public')->exists($profile->cover_page_image)) {
                Storage::disk('public')->delete($profile->cover_page_image);
            }
            $profile->cover_page_image = $request->file('cover_page_image')->store('profiles', 'public');
        }

        $profile->save();


        //  Handle role-specific updates
        if ($request->filled('status')) {
            $status = $request->status;

            $roleNameMap = [
                'vet' => 'veterinary_doctor',
                'vet_clinic' => 'veterinary_clinic',
                'vet_paraprofessional' => 'veterinary_paraprofessional',
            ];

            if (isset($roleNameMap[$status])) {
                $role = Role::where('name', $roleNameMap[$status])->first();

                if ($role) {
                    // Attach role to pivot if not exists
                    $user->roles()->syncWithoutDetaching([$role->id]);

                    // Update active role
                    $user->active_role_id = $role->id;
                    $user->save();
                }
            }

            switch ($status) {
                case 'vet':
                    $roleData = $request->only(['practice_license_num', 'specialty', 'list_them', 'address', 'longitude', 'latitude']);
                    $vet = VeterinaryDoctor::firstOrNew(['user_id' => $user->id]);

                    if (!$vet->exists) {
                        $vet->role = 'Veterinarian';
                        $vet->is_approved = 0;
                        $vet->availability = false;
                        $vet->view = 0;
                    }

                    $vet->fill($roleData);
                    $vet->save();
                    break;

                case 'vet_clinic':
                    $roleData = $request->only(['practice_license_num', 'clinic_name', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude']);
                    $clinic = VeterinaryClinic::firstOrNew(['user_id' => $user->id]);

                    if (!$clinic->exists) {
                        $clinic->role = 'Veterinary Clinic';
                        $clinic->is_approved = 0;
                        $clinic->availability = false;
                        $clinic->view = 0;
                    }

                    $clinic->fill($roleData);
                    $clinic->save();
                    break;

                case 'vet_paraprofessional':
                    $roleData = $request->only(['name_of_institution', 'graduation_year', 'expected_year_of_graduation', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude']);
                    $para = VeterinaryParaprofessional::firstOrNew(['user_id' => $user->id]);

                    if (!$para->exists) {
                        $para->role = 'Veterinary Paraprofessional';
                        $para->is_approved = 0;
                        $para->availability = false;
                        $para->view = 0;
                    }

                    $para->fill($roleData);
                    $para->save();
                    break;
            }
        }


        return response()->json(['message' => 'Profile updated successfully']);
    }
}
// add image
// symptons
