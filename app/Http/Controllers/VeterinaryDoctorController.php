<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Role;
use App\Models\User;
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
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        DB::beginTransaction();
        if ($user->activeRole && $user->activeRole->name === 'veterinary_doctor') {
            DB::rollBack();
            return response()->json(['message' => 'You already have a veterinary doctor role.'], 400);
        }

        $veterinaryDoctorRole = Role::where('name', 'veterinary_doctor')->first();
        if (!$veterinaryDoctorRole) {
            DB::rollBack();
            return response()->json(['message' => 'Role not found.'], 404);
        }

        if ($user->hasRole('veterinary_doctor')) {
            $user->update([
                'active_role_id' => $veterinaryDoctorRole->id
            ]);

            DB::commit();

            $user->refresh();
            return response()->json(['message' => 'Your active role has been changed to veterinary doctor.'], 200);
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


            $user->roles()->attach($veterinaryDoctorRole->id);
            $user->update([
                'active_role_id' => $veterinaryDoctorRole->id,
            ]);

            DB::commit();
            $user->refresh();
            // log activity via private helper
            $user->logActivity(
                action: 'create_veterinary_doctor',
                title: 'Created Veterinary Doctor Profile',
                detail: "User {$user->first_name} {$user->last_name} created a veterinary doctor profile",
                meta: ['veterinary_doctor_id' => $veterinaryDoctor->id]
            );

            // notify admins
            $admins = Admin::all();
            foreach ($admins as $admin) {
                $this->createAdminNotification(
                    $admin->id,
                    "New Veterinary Doctor Pending Approval",
                    "vet_profile",
                    "A new veterinary doctor profile has been created by {$user->first_name} {$user->last_name}.",
                    ['doctor_id' => $veterinaryDoctor->id, 'user_id' => $user->id, 'staff_id' => $admin->staff_id]
                );
            }

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
        $veterinaryDoctors = VeterinaryDoctor::with([
            'user:id,email,first_name,last_name,phone_num,state,country,active_role_id',
            'ratings:id,rateable_id,rating,comment',
            'user.profile:id,user_id,profile_image'
        ])
            ->where('is_approved', 1)
            ->select('id', 'user_id', 'specialty', 'list_them', 'address', 'longitude', 'latitude', 'role', 'availability', 'created_at', 'is_approved')
            ->paginate(10);

        // add average rating to each veterinary doctor
        $veterinaryDoctors->getCollection()->transform(function ($doctor) {
            $doctor->average_rating = $doctor->ratings ? round($doctor->ratings->avg('rating') ?? 0, 1) : 0;
            return $doctor;
        });

        return response()->json(['message' => "success", 'veterinary_doctors' => $veterinaryDoctors], 200);
    }

    // get veterinary doctor base on search query
    public function searchVeterinaryDoctors(Request $request)
    {
        $query = $request->input('query');
        if (!$query) {
            return response()->json(['veterinary_doctors' => []], 200);
        }

        $veterinaryDoctors = VeterinaryDoctor::join('users_registration as u', 'veterinary_doctor.user_id', '=', 'u.id')
            ->with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->where('veterinary_doctor.is_approved', 1)
            ->when($query, function ($q) use ($query) {
                $q->where('veterinary_doctor.specialty', 'like', "%$query%")
                    ->orWhere('veterinary_doctor.list_them', 'like', "%$query%")
                    ->orWhere('veterinary_doctor.address', 'like', "%$query%")
                    ->orWhere('u.first_name', 'like', "%$query%")
                    ->orWhere('u.last_name', 'like', "%$query%")
                    ->orWhere('u.email', 'like', "%$query%");
            })
            ->select('veterinary_doctor.id', 'veterinary_doctor.user_id', 'veterinary_doctor.specialty', 'veterinary_doctor.list_them', 'veterinary_doctor.address', 'veterinary_doctor.longitude', 'veterinary_doctor.latitude', 'veterinary_doctor.role', 'veterinary_doctor.availability', 'veterinary_doctor.is_approved')
            ->distinct()
            ->paginate(10);

        // add average rating to each veterinary doctor
        $veterinaryDoctors->getCollection()->transform(function ($doctor) {
            $doctor->average_rating = $doctor->ratings ? round($doctor->ratings->avg('rating') ?? 0, 1) : 0;
            return $doctor;
        });

        return response()->json(['message' => "success", 'veterinary_doctors' => $veterinaryDoctors], 200);
    }

    // get veterinary doctor by longitude and latitude
    public function getVeterinaryDoctorsByLocation(Request $request)
    {
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        if (!$latitude || !$longitude) {
            return response()->json(['message' => 'Latitude and Longitude are required.'], 400);
        }

        $doctors = VeterinaryDoctor::with([
            'user:id,email,first_name,last_name,phone_num,state,country',
            'user.profile:id,user_id,profile_image,bio,cover_page_image',
            'ratings:id,rateable_id,rating,comment'
        ])
            ->nearest($latitude, $longitude)
            ->paginate(10);

        // attach average rating
        $doctors->transform(function ($doctor) {
            $doctor->average_rating = $doctor->ratings ? round($doctor->ratings->avg('rating') ?? 0, 1) : 0;
            return $doctor;
        });

        return response()->json(['message' => "success", 'veterinary_doctors' => $doctors], 200);
    }


    // get veterinary doctor by id
    public function getVeterinaryDoctorById($id)
    {
        $user = auth()->user();

        $veterinaryDoctor = VeterinaryDoctor::with([
            'user:id,email,first_name,last_name,phone_num,state,country',
            'ratings:id,user_id,rateable_id,rating,comment',
            'user.profile:id,user_id,bio,profile_image,cover_page_image',
            'user.media:id,user_id,file_path',
            'ratings.user:id,first_name,last_name,active_role_id',
            'ratings.user.profile:id,user_id,profile_image'
        ])
            ->select('id', 'user_id', 'specialty', 'list_them', 'address', 'availability', 'is_approved', 'role', 'view', 'created_at')
            ->where('id', $id)
            ->first();

        if (!$veterinaryDoctor) {
            return response()->json(['message' => 'Veterinary doctor not found.'], 404);
        }

        // check if user is admin by email
        $isAdmin = $user && Admin::where('email', $user->email)->exists();

        // increment views if not self and not admin
        if ($user && (int) $user->id !== (int) $veterinaryDoctor->user_id && !$isAdmin) {
            $veterinaryDoctor->recordView();
        }

        $veterinaryDoctor->average_rating = $veterinaryDoctor->averageRating();

        return response()->json(['veterinary_doctor' => $veterinaryDoctor], 200);
    }


    public function verify($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $doctor = VeterinaryDoctor::findOrFail($id);
        $user = User::find($doctor->user_id);

        if ($user) {
            $doctor->is_approved = 1;
            $doctor->save();

            // Notify the user
            $user->sendNotification(
                'veterinary_doctor_verified',
                'Verified Veterinary Doctor',
                "Dr {$user->first_name}, You have been verified.",
                ['veterinary_doctor_id' => $doctor->id, 'user_id' => $user->id]
            );

            $user->notify(new \App\Notifications\AdminActionNotification(
                'License Number Verified',
                'Congratulations ' . $user->first_name . ', We are so pleased to inform you that your License Number has been verified and your account is now visible on our platform.'
            ));
        }

        // Log admin activity
        $this->createAdminActivity(
            $admin->id,
            'veterinary_doctor_verified',
            'Verified Veterinary Doctor',
            "Admin {$admin->name} verified Dr {$user->first_name} (ID: {$doctor->id}).",
            ['doctor_id' => $doctor->id, 'user_id' => $doctor->user_id, 'staff_id' => $admin->staff_id]
        );

        return response()->json(['message' => 'Veterinary Doctor Verified'], 200);
    }
}
