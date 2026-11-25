<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\livestock_farmer;
use App\Models\User;
use App\Traits\BroadcastsAdminEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FarmController extends Controller
{
    use BroadcastsAdminEvents;

    private function generateFarmId($userId)
    {
        $farmCount = Farm::where('user_id', $userId)->count();
        $randomNumber = mt_rand(100000, 999999);
        $letter = chr(97 + $farmCount);

        return "FM{$randomNumber}{$letter}";
    }

    public function addNewFarm(Request $request)
    {
        $request->validate([
            'farm_name'      => 'required|string|max:50',
            'location'       => 'required|string|max:255',
            'no_of_worker'   => 'required|numeric|min:0',
            'livestock_type' => 'required|string|max:50',
            'no_of_livestock' => 'required|numeric|min:0',
            'sex'            => 'nullable|string',
            'age'            => 'nullable|numeric',
            'picture'        => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'description'    => 'nullable|string|max:255',
        ]);

        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 403);
        }

        $isVeterinary = $user->activeRole && $user->activeRole->name === 'veterinary_doctor';
        $isLivestockFarmer = $user->activeRole && $user->activeRole->name === 'livestock_farmer';

        if (!($isLivestockFarmer || $isVeterinary)) {
            return response()->json(['message' => 'You cannot create a farm'], 403);
        }

        $farmId = $this->generateFarmId($user->id);

        $imageName = null;
        if ($request->hasFile('picture')) {
            $imageName = $request->file('picture')->store('farm_pictures', 'public');
        }

        $farm = Farm::create([
            'user_id'        => $user->id,
            'farm_name'      => $request->farm_name,
            'location'       => $request->location,
            'farm_id'        => $farmId,
            'no_of_worker'   => $request->no_of_worker,
            'livestock_type' => $request->livestock_type,
            'no_of_livestock' => $request->no_of_livestock,
            'sex'            => $request->sex,
            'age'            => $request->age,
            'picture'        => $imageName,
            'description'    => $request->description
        ]);

        Cache::forget("user_farms_{$user->id}");

        // Log + Notify
        $user->logActivity(
            'farm_created',
            'Farm Created',
            "A new farm '{$farm->farm_name}' (ID: {$farm->farm_id}) was created by {$user->first_name}.",
            ['farm_id' => $farm->id]
        );



        return response()->json(['message' => "A new farm created", 'farm' => $farm], 201);
    }

    public function updateFarm(Request $request, $id)
    {
        $request->validate([
            'farm_name'      => 'sometimes|string|max:50',
            'location'       => 'sometimes|string|max:255',
            'no_of_worker'   => 'sometimes|numeric|min:0',
            'livestock_type' => 'sometimes|string|max:50',
            'no_of_livestock' => 'sometimes|numeric|min:0',
            'sex'            => 'sometimes|string',
            'age'            => 'sometimes|numeric',
            'picture'        => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'description'    => 'sometimes|string|max:255',
        ]);

        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $farm = Farm::findOrFail($id);
        if ($farm->user_id != $user->id) {
            return response()->json(['message' => 'You are not authorized to update this farm.'], 403);
        }

        if($farm->disabled) return response()->json(['message' => "cannot update a disabled farm."], 403);

        if ($request->hasFile('picture')) {
            if ($farm->picture) {
                Storage::disk('public')->delete($farm->picture);
            }
            $farm->picture = $request->file('picture')->store('farm_pictures', 'public');
        }

        $farm->fill($request->only([
            'farm_name',
            'location',
            'no_of_worker',
            'livestock_type',
            'no_of_livestock',
            'sex',
            'age',
            'description'
        ]));
        $farm->updated_at = Carbon::now();
        $farm->save();

        Cache::forget("user_farms_{$user->id}");

        // Log + Notify
        $user->logActivity(
            'farm_updated',
            'Farm Updated',
            "Farm '{$farm->farm_name}' (ID: {$farm->farm_id}) was updated by {$user->first_name}.",
            ['farm_id' => $farm->id]
        );


        return response()->json(['message' => "Farm {$farm->farm_id} updated successfully.", 'farm' => $farm]);
    }

    public function getUserFarms()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $farms = Cache::remember("user_farms_{$user->id}", now()->addMinutes(10), function () use ($user) {
            return Farm::where('user_id', $user->id)
                ->with('user', 'user.profile')
                ->orderBy('created_at', 'asc')->paginate(10);
        });

        return response()->json(['farms' => $farms], 200);
    }

    public function getFarmById($id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $farm = Cache::remember("farm_{$id}", now()->addMinutes(10), function () use ($id) {
            return Farm::with(['user', 'user.profile'])->find($id);
        });


        if (!$farm) {
            return response()->json(['message' => 'Farm not found.'], 404);
        }

        if ($farm->user_id != $user->id) {
            return response()->json(['message' => 'You are not authorized to view this farm.'], 403);
        }

        return response()->json(['farm' => $farm], 200);
    }

    public function deleteFarm($id)
    {
        try {
            $user = auth('user')->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $farm = Farm::find($id);
            if (!$farm) {
                return response()->json(['message' => 'Farm not found.'], 404);
            }

            if ((int) $farm->user_id !== (int) $user->id) {
                return response()->json(['message' => 'You are not authorized to delete this farm.'], 403);
            }

            if($farm->disabled) return response()->json(['message' => "cannot delete a disabled farm."], 403);

            if ($farm->picture) {
                Storage::disk('public')->delete($farm->picture);
            }

            $farmName = $farm->farm_name;
            $farmId = $farm->farm_id;
            $farm->delete();

            Cache::forget("user_farms_{$user->id}");
            Cache::forget("farm_{$id}");

            // Log + Notify
            $user->logActivity(
                'farm_deleted',
                'Farm Deleted',
                "Farm '{$farmName}' (ID: {$farmId}) was deleted by {$user->first_name}.",
                ['farm_id' => $farmId]
            );


            return response()->json(['message' => 'Farm deleted successfully.'], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while deleting the farm.',
                'error'   => $th->getMessage()
            ], 500);
        }
    }

    public function disableFarm($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $farm = Farm::findOrFail($id);
        $user = User::find($farm->user_id);

        if ($user) {
            $farm->disabled = true;
            $farm->save();

            // Notify the user
            $user->sendNotification(
                'farm',
                'Farm Disabled',
                "Your farm <strong>{$farm->farm_name}</strong> (ID: {$farm->farm_id}) has been disabled by an administrator. Please contact support for further details.",
                ['farm_id' => $farm->farm_id, 'user_id' => $user->id]
            );

            $user->notify(new \App\Notifications\AdminActionNotification(
                'Farm Disabled by Administrator',
                'Dear ' . $user->first_name . ', we regret to inform you that your farm "' . $farm->farm_name . '" (ID: ' . $farm->farm_id . ') has been disabled by an administrator. Please contact support if you believe this is an error or wish to appeal.'
            ));
        }

        // Log admin activity
        $this->createAdminActivity(
            $admin->id,
            'farm_disabled',
            'Farm Disabled',
            "Admin {$admin->name} disabled farm '{$farm->farm_name}' (ID: {$farm->farm_id}) belonging to {$user->first_name}.",
            ['farm_id' => $farm->farm_id, 'user_id' => $user->id, 'staff_id' => $admin->staff_id]
        );


        Cache::forget("user_farms_{$user->id}");
        Cache::forget("farm_{$id}");


        return response()->json(['message' => 'Farm disabled'], 200);
    }

    public function enableFarm($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $farm = Farm::findOrFail($id);
        $user = User::find($farm->user_id);

        if ($user) {
            $farm->disabled = false;
            $farm->save();

            // Notify the user
            $user->sendNotification(
                'farm',
                'Farm Enabled',
                "Your farm <strong>{$farm->farm_name}</strong> (ID: {$farm->farm_id}) has been enabled by an administrator.",
                ['farm_id' => $farm->farm_id, 'user_id' => $user->id]
            );

            $user->notify(new \App\Notifications\AdminActionNotification(
                'Farm Enabled by Administrator',
                'Dear ' . $user->first_name . ', we are pleased to inform you that your farm "' . $farm->farm_name . '" (ID: ' . $farm->farm_id . ') has been enabled by an administrator. You can now access all features associated with your farm.'
            ));
        }

        // Log admin activity
        $this->createAdminActivity(
            $admin->id,
            'farm_enabled',
            'Farm Enabled',
            "Admin {$admin->name} enabled farm '{$farm->farm_name}' (ID: {$farm->farm_id}) belonging to {$user->first_name}.",
            ['farm_id' => $farm->farm_id, 'user_id' => $user->id, 'staff_id' => $admin->staff_id]
        );
    }
}
