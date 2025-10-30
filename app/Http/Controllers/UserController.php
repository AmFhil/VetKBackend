<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\livestock_farmer;
use App\Models\Others;
use App\Models\Pet_Owner;
use App\Models\Profiles;
use App\Models\Role;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Models\VeterinaryClinic;
use App\Models\VeterinaryDoctor;
use App\Models\VeterinaryParaprofessional;
use App\Services\UpdateProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    // In VeterinaryDoctorController.php
    public function getNearestVeterinaryDoctor(Request $request)
    {
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $page = (int) $request->input('page', 1);

        if (!$latitude || !$longitude) {
            return response()->json(['message' => 'Latitude and Longitude are required.'], 400);
        }

        // Apply auth if page > 1
        if ($page > 1 && !auth('sanctum')->check()) {
            return response()->json(['message' => 'Unauthorized. Please log in.'], 401);
        }

        $doctors = VeterinaryDoctor::with([
            'user:id,email,first_name,last_name,phone_num,state,country',
            'user.profile:id,user_id,profile_image,bio,cover_page_image',
            'ratings:id,rateable_id,rating,comment'
        ])
            ->where('is_approved', 1)
            ->nearest($latitude, $longitude)
            ->paginate(5);

        $doctors->getCollection()->transform(function ($doctor) {
            $doctor->average_rating = $doctor->averageRating();
            return $doctor;
        });

        return response()->json(['message' => 'success', 'veterinary_doctors' => $doctors], 200);
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

        return response()->json(['message' => 'success', 'veterinary_doctors' => $veterinaryDoctors], 200);
    }

    //update profile
    public function updateProfiles(Request $request)
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
                $user->sendNotification(
                    type: 'security',
                    title: 'Password Changed',
                    content: 'Your password has been successfully updated.'
                );
            }

            if (!$passwordMatches) {
                return response()->json(['message' => 'Current password is incorrect'], 422);
            }

            // set new password
            $user->password = Hash::make($request->password);
            $user->password_algorithm = 'bcrypt';
            $user->sendNotification(
                type: 'security',
                title: 'Password Changed',
                content: 'Your password has been successfully updated.'
            );
        }

        //  Update email only if provider == null
        if ($request->filled('email')) {
            if (!is_null($user->provider)) {
                return response()->json(['message' => 'You cannot change email for social login accounts'], 422);
            }
            $user->email = $request->email;
            $user->sendNotification(
                type: 'security',
                title: 'Email Updated',
                content: 'Your email address has been updated.'
            );
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

            $user->logActivity(
                action: 'update_profile_image',
                title: 'Profile Image Updated',
                detail: "User {$user->first_name} {$user->last_name} updated their profile image",
                meta: ['profile_image' => $profile->profile_image]
            );

            $user->sendNotification(
                type: 'profile',
                title: 'Profile Image Updated',
                content: 'Your profile picture was updated successfully.'
            );
        }

        if ($request->hasFile('cover_page_image')) {
            // delete old cover image if exists
            if ($profile->cover_page_image && Storage::disk('public')->exists($profile->cover_page_image)) {
                Storage::disk('public')->delete($profile->cover_page_image);
            }
            $profile->cover_page_image = $request->file('cover_page_image')->store('profiles', 'public');

            $user->logActivity(
                action: 'update_cover_image',
                title: 'Cover Image Updated',
                detail: "User {$user->first_name} {$user->last_name} updated their cover page image",
                meta: ['cover_page_image' => $profile->cover_page_image]
            );
        }

        $profile->save();


        //  Handle role-specific updates
        if ($request->filled('status')) {
            $status = $request->status;

            $roleNameMap = [
                'vet' => 'veterinary_doctor',
                'vet_clinic' => 'veterinary_clinic',
                'vet_paraprofessional' => 'veterinary_paraprofessional',
                'vendor' => 'vendor',
                'pet' => 'pet_owner',
                'farmer' => 'livestock_farmer',
                'others' => 'others'
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
                    $user->logActivity(
                        action: 'create_veterinary_doctor',
                        title: 'Created Veterinary Doctor Profile',
                        detail: "User {$user->first_name} {$user->last_name} created a veterinary doctor profile",
                        meta: ['veterinary_doctor_id' => $vet->id]
                    );
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
                    $user->logActivity(
                        action: 'create_veterinary_clinic',
                        title: 'Created Veterinary Clinic Profile',
                        detail: "User {$user->first_name} {$user->last_name} created a veterinary clinic profile",
                        meta: ['veterinary_clinic_id' => $clinic->id]
                    );
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
                    $user->logActivity(
                        action: 'create_veterinary_paraprofessional',
                        title: 'Created Veterinary Paraprofessional Profile',
                        detail: "User {$user->first_name} {$user->last_name} created a veterinary paraprofessional profile",
                        meta: ['veterinary_paraprofessional_id' => $para->id]
                    );
                    break;
                case 'vendor':
                    $ven = Vendor::firstOrNew(['user_id' => $user->id]);
                    if (!$ven->exists) {
                        $ven->role = 'vendor';
                        $ven->save();
                        $user->logActivity(
                            'create_vendor',
                            'Created Vendor Profile',
                            "User {$user->first_name} {$user->last_name} created a vendor profile",
                            ['vendor_id' => $ven->id]
                        );
                    }
                    break;
                case 'pet':
                    $pet = Pet_Owner::firstOrNew(['user_id' => $user->id]);
                    if (!$pet->exists) {
                        $pet->role = 'pet_owner';
                        $pet->save();
                        $user->logActivity(
                            'create_pet_owner',
                            'Created Pet Owner Profile',
                            "User {$user->first_name} {$user->last_name} created a pet owner profile",
                            ['pet_owner_id' => $pet->id]
                        );
                    }
                    break;
                case 'farmer':
                    $farmer = livestock_farmer::firstOrNew(['user_id' => $user->id]);
                    if (!$farmer->exists) {
                        $farmer->role = 'livestock_farmer';
                        $farmer->save();
                        $user->logActivity(
                            'create_livestock_farmer',
                            'Created Livestock Farmer Profile',
                            "User {$user->first_name} {$user->last_name} created a livestock farmer profile",
                            ['livestock_farmer_id' => $farmer->id]
                        );
                    }
                    break;
                case 'others':
                    $others = Others::firstOrNew(['user_id' => $user->id]);
                    if (!$others->exists) {
                        $others->role = 'others';
                        $others->save();
                        $user->logActivity(
                            'create_others',
                            'Created Others Profile',
                            "User {$user->first_name} {$user->last_name} created an others profile",
                            ['others_id' => $others->id]
                        );
                    }
                    break;
            }
        }

        $user->logActivity('update', 'Profile Updated', 'User updated their profile');
        $user->sendNotification('profile', 'Profile Updated', 'Your profile was successfully updated.');



        return response()->json(['message' => 'Profile updated successfully'], 200);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = auth('user')->user(); // Auth check is in FormRequest

        try {
            app(UpdateProfileService::class)->execute($user, $request->validated());
            return response()->json(['message' => 'Profile updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    public function secureEndpoint($email): JsonResponse
    {
        $user = User::where('email', $email)
            ->select('email_verified_at')
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        return response()->json([
            'message' => 'success',
            'email_verified_at' => $user->email_verified_at,
        ], 200);
    }

    // get user activity
    public function getUserActivity()
    {
        $user = Auth::user();

        $userActivity = UserActivity::where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->paginate(10);

        return response()->json($userActivity, 200);
    }

    // get user notification
    public function getUserNotification()
    {
        $user = Auth::user();

        $userNotification = UserNotification::where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        return response()->json($userNotification, 200);
    }
}
// add image
// symptons
