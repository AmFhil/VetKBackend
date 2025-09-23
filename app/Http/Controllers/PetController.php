<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\Pet_Owner;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PetController extends Controller
{
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

        $user->logActivity('pet_created', 'Pet Created', "You added a new pet: {$pet->pet_name}", [
            'pet_id' => $pet->id,
        ]);
        $user->sendNotification('pet', 'New Pet Added', "Your pet {$pet->pet_name} has been added.", [
            'pet_id' => $pet->id,
        ]);

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

        $user->logActivity('pet_updated', 'Pet Updated', "Pet {$pet->pet_name} updated.", [
            'pet_id' => $pet->id,
        ]);
        $user->sendNotification('pet', 'Pet Updated', "Your pet {$pet->pet_name} has been updated.", [
            'pet_id' => $pet->id,
        ]);

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

        $pet = Pet::find($id);
        if (!$pet) {
            return response()->json(['message' => 'Pet not found.'], 404);
        }
        if ($pet->user_id != $user->id) {
            return response()->json(['message' => 'You are not authorized to view this pet.'], 403);
        }

        Cache::remember("pet_{$id}", now()->addMinutes(30), fn() => $pet);

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

            $user->logActivity('pet_deleted', 'Pet Deleted', "Pet {$pet_name} deleted.", [
                'pet_id' => $pet->id,
            ]);
            $user->sendNotification('pet', 'Pet Deleted', "Your pet {$pet_name} has been deleted.", [
                'pet_id' => $pet->id,
            ]);

            return response()->json(['message' => 'Pet deleted successfully.'], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while deleting the pet.',
                'error'   => $th->getMessage()
            ], 500);
        }
    }
}
