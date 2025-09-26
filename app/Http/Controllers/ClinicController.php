<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Clinic;
use App\Models\User;
use App\Models\VeterinaryClinic;
use App\Models\VeterinaryDoctor;
use App\Traits\BroadcastsAdminEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ClinicController extends Controller
{
    use BroadcastsAdminEvents;

    public function addNewClinic(Request $request)
    {
        $request->validate([
            'license_number'   => 'required|string|max:50',
            'clinic_name'      => 'required|string|max:50',
            'clinic_speciality' => 'required|array',
            'email'            => 'required|email',
            'phone_number'     => 'required|numeric',
            'location'         => 'required|string|max:50',
            'availability'     => 'required|boolean',
            'picture'          => 'required|image|mimes:jpeg,jpg,png|max:2048',
            'longitude'        => 'required|numeric',
            'latitude'         => 'required|numeric',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 403);
        }

        $isVeterinaryClinic = VeterinaryClinic::where('user_id', $user->id)->exists();
        $isVeterinaryDoctor = VeterinaryDoctor::where('user_id', $user->id)->exists();

        if (!($isVeterinaryClinic || $isVeterinaryDoctor)) {
            return response()->json(['message' => 'You are not authorized to create a clinic'], 403);
        }

        $imageName = $request->hasFile('picture')
            ? $request->file('picture')->store('clinic_pictures', 'public')
            : null;

        $clinic = Clinic::create([
            'user_id'         => $user->id,
            'license_number'  => $request->license_number,
            'license_status'  => 'pending',
            'clinic_name'     => $request->clinic_name,
            'clinic_speciality' => $request->clinic_speciality,
            'email'           => $request->email,
            'phone_number'    => $request->phone_number,
            'location'        => $request->location,
            'availability'    => $request->availability,
            'picture'         => $imageName,
            'longitude'       => $request->longitude,
            'latitude'        => $request->latitude,
        ]);

        Cache::forget("user_clinics_{$user->id}");

        //  User Activity + Notification
        $user->logActivity(
            'clinic_created',
            'Clinic Created',
            "A new clinic '{$clinic->clinic_name}' (ID: {$clinic->id}) was created by {$user->first_name}.",
            ['clinic_id' => $clinic->id]
        );

        $user->sendNotification(
            'clinic',
            'Clinic Created',
            "Your clinic <strong>{$clinic->clinic_name}</strong> has been created and is pending license verification.",
            ['clinic_id' => $clinic->id]
        );


        // Admin Notifications + Activities
        $admins = Admin::all();
        foreach ($admins as $admin) {
            $this->createAdminNotification(
                $admin->id,
                "New Clinic Pending Approval",
                "Clinic",
                "Clinic {$clinic->clinic_name} was created by {$user->first_name} {$user->last_name}.",
                ['clinic_id' => $clinic->id, 'user_id' => $user->id]
            );

            $this->createAdminActivity(
                $admin->id,
                "clinic_review",
                "New Clinic Created",
                "Review clinic {$clinic->clinic_name} created by user {$user->first_name}",
                ['clinic_id' => $clinic->id]
            );
        }

        return response()->json(['message' => "A new clinic created", 'clinic' => $clinic], 201);
    }

    public function updateClinic(Request $request, $id)
    {
        $request->validate([
            'license_number'   => 'sometimes|string|max:50',
            'clinic_name'      => 'sometimes|string|max:50',
            'clinic_speciality' => 'sometimes|array',
            'email'            => 'sometimes|email',
            'phone_number'     => 'sometimes|numeric',
            'location'         => 'sometimes|string|max:50',
            'availability'     => 'sometimes|boolean',
            'picture'          => 'sometimes|image|mimes:jpeg,jpg,png|max:2048',
            'longitude'        => 'sometimes|numeric',
            'latitude'         => 'sometimes|numeric',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $clinic = Clinic::findOrFail($id);
        if ($clinic->user_id !== $user->id) {
            return response()->json(['message' => 'You are not authorized to update this clinic.'], 403);
        }

        if ($request->hasFile('picture')) {
            if ($clinic->picture) {
                Storage::disk('public')->delete($clinic->picture);
            }
            $clinic->picture = $request->file('picture')->store('clinic_pictures', 'public');
        }

        $clinic->fill($request->only([
            'clinic_name',
            'clinic_speciality',
            'email',
            'phone_number',
            'location',
            'availability',
            'longitude',
            'latitude'
        ]));
        $clinic->updated_at = Carbon::now();
        $clinic->save();

        Cache::forget("user_clinics_{$user->id}");

        // User Activity + Notification
        $user->logActivity(
            'clinic_updated',
            'Clinic Updated',
            "Clinic '{$clinic->clinic_name}' (ID: {$clinic->id}) was updated by {$user->first_name}.",
            ['clinic_id' => $clinic->id]
        );

        $user->sendNotification(
            'clinic',
            'Clinic Updated',
            "Your clinic <strong>{$clinic->clinic_name}</strong> (ID: {$clinic->id}) has been updated successfully.",
            ['clinic_id' => $clinic->id]
        );


        return response()->json(['message' => "Clinic {$clinic->clinic_name} updated successfully.", 'clinic' => $clinic], 200);
    }

    public function deleteClinic($id)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $clinic = Clinic::find($id);
            if (!$clinic) {
                return response()->json(['message' => 'Clinic not found.'], 404);
            }

            if ($clinic->user_id !== $user->id) {
                return response()->json(['message' => 'You are not authorized to delete this clinic.'], 403);
            }

            if ($clinic->picture) {
                Storage::disk('public')->delete($clinic->picture);
            }

            $clinicName = $clinic->clinic_name;
            $clinic->delete();

            Cache::forget("user_clinics_{$user->id}");
            Cache::forget("clinic_{$id}");

            // User Activity + Notification
            $user->logActivity(
                'clinic_deleted',
                'Clinic Deleted',
                "Clinic '{$clinicName}' (ID: {$clinic->id}) was deleted by {$user->first_name}.",
                ['clinic_id' => $id]
            );

            $user->sendNotification(
                'clinic',
                'Clinic Deleted',
                "Your clinic <strong>{$clinicName}</strong> (ID: {$clinic->id}) has been permanently deleted.",
                ['clinic_id' => $id]
            );


            return response()->json(['message' => 'Clinic deleted successfully.'], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while deleting the clinic.',
                'error'   => $th->getMessage()
            ], 500);
        }
    }

    public function getUserClinics()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $clinics = Cache::remember("user_clinics_{$user->id}", now()->addMinutes(10), function () use ($user) {
            return Clinic::where('user_id', $user->id)->orderBy('created_at', 'asc')->get();
        });

        return response()->json(['clinics' => $clinics], 200);
    }

    public function getClinicById($id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $clinic = Cache::remember("clinic_{$id}", now()->addMinutes(10), fn() => Clinic::find($id));

        if (!$clinic) {
            return response()->json(['message' => 'clinic not found.'], 404);
        }

        if ($clinic->user_id !== $user->id) {
            return response()->json(['message' => 'You are not authorized to view this clinic.'], 403);
        }

        return response()->json(['clinic' => $clinic], 200);
    }

    public function searchClinics(Request $request)
    {
        $query = $request->input('query', '');

        if (!$query) {
            return response()->json(['clinics' => []], 200);
        }

        $clinics = Clinic::where('clinic_name', 'like', "%$query%")
            ->orWhereJsonContains('clinic_speciality', $query)
            ->orWhere('location', 'like', "%$query%")
            ->paginate(10);

        return response()->json(['clinics' => $clinics], 200);
    }

    // Admin verify clinic license
    public function verify($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $clinic = Clinic::findOrFail($id);
        $clinic->license_status = 'verified';
        $clinic->save();

        $user = User::find($clinic->user_id);

        if ($user) {
            // Notify the user
            $user->sendNotification(
                'clinic',
                'Clinic Verified',
                "Your clinic <strong>{$clinic->clinic_name}</strong> (ID: {$clinic->id}) has been successfully verified and is now visible on the platform.",
                ['clinic_id' => $clinic->id, 'user_id' => $user->id]
            );

            $user->logActivity(
                'clinic_verified',
                'Clinic Verified',
                "Clinic '{$clinic->clinic_name}' (ID: {$clinic->id}) was verified by Admin {$admin->name}.",
                ['clinic_id' => $clinic->id]
            );

            $user->notify(new \App\Notifications\AdminActionNotification(
                'License Number Verified',
                'Congratulations ' . $user->first_name . ', We are so pleased to inform you that your Clinic License Number has been verified and your clinic is now visible on our platform.'
            ));

            $this->createAdminActivity(
                $admin->id,
                'clinic_verified',
                'Clinic License Verified',
                "Admin {$admin->name} verified clinic '{$clinic->clinic_name}' (ID: {$clinic->id}).",
                ['clinic_id' => $clinic->id, 'user_id' => $clinic->user_id, 'staff_id' => $admin->staff_id]
            );
        }


        Cache::forget("user_clinics_{$user->id}");
        Cache::forget("clinic_{$id}");

        return response()->json(['message' => 'Clinic Verified']);
    }

    public function disableClinic($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $clinic = Clinic::findOrFail($id);
        $user = User::find($clinic->user_id);

        if ($user) {
            $clinic->disabled = true;
            $clinic->save();
            // Notify the user
            $user->sendNotification(
                'clinic',
                'Clinic Disabled',
                "Your clinic <strong>{$clinic->clinic_name}</strong> (ID: {$clinic->id}) has been disabled by an administrator. Please contact support for further details.",
                ['clinic_id' => $clinic->id, 'user_id' => $user->id]
            );

            // Log user activity
            $user->logActivity(
                'clinic_disabled',
                'Clinic Disabled',
                "Your clinic '{$clinic->clinic_name}' (ID: {$clinic->id}) was disabled by an administrator.",
                ['clinic_id' => $clinic->id]
            );

            $user->notify(new \App\Notifications\AdminActionNotification(
                'Clinic Disabled by Administrator',
                'Dear ' . $user->first_name . ', we regret to inform you that your clinic "' . $clinic->clinic_name . '" (ID: ' . $clinic->id . ') has been disabled by an administrator. Please contact support if you believe this is an error or wish to appeal.'
            ));
        }

        // Log admin activity
        $this->createAdminActivity(
            $admin->id,
            'clinic_disabled',
            'Clinic Disabled',
            "Admin {$admin->name} disabled clinic '{$clinic->clinic_name}' (ID: {$clinic->id}) belonging to {$user->first_name}.",
            ['clinic_id' => $clinic->id, 'user_id' => $clinic->user_id, 'staff_id' => $admin->staff_id]
        );

        Cache::forget("user_clinics_{$user->id}");
        Cache::forget("clinic_{$id}");


        return response()->json(['message' => 'Clinic disabled'], 200);
    }
}
