<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\Pet_Owner;
use App\Models\User;
use App\Traits\BroadcastsAdminEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PetController extends Controller
{
    use BroadcastsAdminEvents;
    // generate pet_id
    private function generatePetId($userId)
    {
        $petCount = Pet::where('user_id', $userId)->count();
        $randomNumber = mt_rand(100000, 999999);
        $letter = chr(97 + $petCount); // 0 => a, 1 => b ...

        return "PT{$randomNumber}{$letter}";
    }

    // handle image upload
    private function handleImageUpload(Request $request, Pet $pet = null)
    {
        if ($request->hasFile('picture')) {
            if ($pet && $pet->picture) {
                Storage::disk('public')->delete($pet->picture);
            }
            return $request->file('picture')->store('pet_pictures', 'public');
        }
        return $pet ? $pet->picture : null;
    }

    // add new pet
    public function addNewPet(Request $request)
    {
        $request->validate([
            'pet_name' => 'required|string|max:50',
            'specie'   => 'required|string|max:50',
            'breed'    => 'required|string|max:50',
            'sex'      => 'required|string|max:10',
            'age'      => 'required|numeric',
            'location' => 'required|string|max:255',
            'picture'  => 'required|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 403);
        }

        $petOwner = Pet_Owner::where('user_id', $user->id)->exists();
        if (!$petOwner) {
            return response()->json(['message' => 'You are not authorized to create a pet'], 403);
        }

        $petId = $this->generatePetId($user->id);
        $imageName = $this->handleImageUpload($request);

        $pet = Pet::create([
            'user_id'  => $user->id,
            'pet_name' => $request->pet_name,
            'pet_id'   => $petId,
            'specie'   => $request->specie,
            'breed'    => $request->breed,
            'sex'      => $request->sex,
            'age'      => $request->age,
            'location' => $request->location,
            'picture'  => $imageName,
        ]);

        Cache::forget("user_pets_{$user->id}");
        Cache::forget("pet_{$pet->id}");

        $user->logActivity(
            'pet_created',
            'Pet Created',
            "A new pet '{$pet->pet_name}' (ID: {$pet->pet_id}) was created by {$user->first_name}.",
            ['pet_id' => $pet->id]
        );

        $user->sendNotification(
            'pet',
            'Pet Created',
            "Your pet <strong>{$pet->pet_name}</strong> (ID: {$pet->pet_id}) has been successfully added to your account.",
            ['pet_id' => $pet->id]
        );


        return response()->json(['message' => "A new pet created", 'pet' => $pet], 201);
    }

    // update pet
    public function updatePet(Request $request, $id)
    {
        $request->validate([
            'pet_name' => 'sometimes|string|max:50',
            'specie'   => 'sometimes|string|max:50',
            'breed'    => 'sometimes|string|max:50',
            'sex'      => 'sometimes|string|max:10',
            'age'      => 'sometimes|numeric',
            'location' => 'sometimes|string|max:255',
            'picture'  => 'sometimes|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $pet = Pet::findOrFail($id);
        if ($pet->user_id != $user->id) {
            return response()->json(['message' => 'You are not authorized to update this pet.'], 403);
        }

        $pet->picture = $this->handleImageUpload($request, $pet);
        $pet->fill($request->only(['pet_name', 'specie', 'breed', 'sex', 'age', 'location']));
        $pet->updated_at = Carbon::now();
        $pet->save();

        Cache::forget("user_pets_{$user->id}");
        Cache::forget("pet_{$id}");

        $user->logActivity(
            'pet_updated',
            'Pet Updated',
            "Pet '{$pet->pet_name}' (ID: {$pet->pet_id}) was updated by {$user->first_name}.",
            ['pet_id' => $pet->id]
        );

        $user->sendNotification(
            'pet',
            'Pet Updated',
            "Your pet <strong>{$pet->pet_name}</strong> (ID: {$pet->pet_id}) has been updated successfully.",
            ['pet_id' => $pet->id]
        );


        return response()->json(['message' => "Pet {$pet->pet_id} updated successfully.", 'pet' => $pet]);
    }

    // get all pets for authenticated user
    public function getUserPets()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $pets = Cache::remember("user_pets_{$user->id}", now()->addMinutes(30), function () use ($user) {
            return Pet::where('user_id', $user->id)
                ->with('user', 'user.activeRole', 'user.profile')
                ->orderBy('created_at', 'asc')->get();
        });

        return response()->json(['pets' => $pets], 200);
    }

    // get pet by id
    public function getPetById($id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $pet = Cache::remember("pet_{$id}", now()->addMinutes(10), function () use ($id) {
            return Pet::with(['user', 'user.activeRole', 'user.profile'])->find($id);
        });

        if (!$pet) {
            return response()->json(['message' => 'Pet not found.'], 404);
        }
        if ($pet->user_id != $user->id) {
            return response()->json(['message' => 'You are not authorized to view this pet.'], 403);
        }


        return response()->json(['pet' => $pet], 200);
    }

    // delete pet
    public function deletePet($id)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $pet = Pet::find($id);
            if (!$pet) {
                return response()->json(['message' => 'Pet not found.'], 404);
            }

            if ($pet->user_id != $user->id) {
                return response()->json(['message' => 'You are not authorized to delete this pet.'], 403);
            }

            if ($pet->picture) {
                Storage::disk('public')->delete($pet->picture);
            }
            $pet_name = $pet->pet_name;
            $pet->delete();

            Cache::forget("user_pets_{$user->id}");
            Cache::forget("pet_{$id}");

            $user->logActivity(
                'pet_deleted',
                'Pet Deleted',
                "Pet '{$pet_name}' (ID: {$pet->pet_id}) was deleted by {$user->first_name}.",
                ['pet_id' => $pet->id]
            );

            $user->sendNotification(
                'pet',
                'Pet Deleted',
                "Your pet <strong>{$pet_name}</strong> (ID: {$pet->pet_id}) has been permanently deleted.",
                ['pet_id' => $pet->id]
            );


            return response()->json(['message' => 'Pet deleted successfully.'], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while deleting the pet.',
                'error'   => $th->getMessage()
            ], 500);
        }
    }

    public function disablePet($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $pet = Pet::findOrFail($id);
        $user = User::where('user_id', $pet->user_id)->exist();

        if ($user) {
            $pet->disabled = true;
            $pet->save();
            // Notify the user
            $user->sendNotification(
                'pet',
                'Pet Disabled',
                "Your pet <strong>{$pet->pet_name}</strong> (ID: {$pet->pet_id}) has been disabled by an administrator. Please contact support for more details.",
                ['pet_id' => $pet->id, 'user_id' => $user->id]
            );

            $user->logActivity(
                'pet_disabled',
                'Pet Disabled',
                "Your pet '{$pet->pet_name}' (ID: {$pet->pet_id}) was disabled by an admin.",
                ['pet_id' => $pet->id]
            );

            $user->notify(new \App\Notifications\AdminActionNotification(
                'Pet Disabled by Administrator',
                'Dear ' . $user->first_name . ', we regret to inform you that your pet "' . $pet->pet_name . '" (ID: ' . $pet->pet_id . ') has been disabled by an administrator. Please contact support if you believe this is an error or wish to appeal.'
            ));

            // Admin-facing
            $this->createAdminActivity(
                $admin->id,
                'pet_disabled',
                'Pet Disabled',
                "Admin {$admin->name} disabled pet '{$pet->pet_name}' (ID: {$pet->pet_id}) belonging to {$user->first_name}.",
                ['pet_id' => $pet->id, 'user_id' => $user->id, 'staff_id' => $admin->staff_id]
            );
        }

        Cache::forget("user_pets_{$user->id}");
        Cache::forget("pet_{$id}");

        return response()->json(['message' => 'Pet disabled'], 200);
    }
}
