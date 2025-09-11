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

    // add new pet
    public function addNewPet(Request $request)
    {
        $request->validate([
            'pet_name' => 'required|string|max:50',
            'specie'   => 'required|string|max:50',
            'breed'    => 'nullable|string|max:50',
            'sex'      => 'nullable|string|max:5',
            'age'      => 'nullable|numeric',
            'location' => 'nullable|string|max:255',
            'picture'  => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 403);
        }

        $petOwner = Pet_Owner::where('user_id', $user->id);

        if(!$petOwner){
            return response()->json(['message' => 'You are not authorize to create a pet'], 403);
        }

        $petId = $this->generatePetId($user->id);

        $imageName = null;
        if ($request->hasFile('picture')) {
            $imageName = $request->file('picture')->store('pet_pictures', 'public');
        }

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

        // clear user pets cache
        Cache::forget("user_pets_{$user->id}");

        return response()->json(['message' => "A new pet created", 'pet' => $pet], 201);
    }

    // update pet
    public function updatePet(Request $request, $id)
    {
        $request->validate([
            'pet_name' => 'sometimes|string|max:50',
            'specie'   => 'sometimes|string|max:50',
            'breed'    => 'sometimes|string|max:50',
            'sex'      => 'sometimes|string|max:5',
            'age'      => 'sometimes|numeric',
            'location' => 'sometimes|string|max:255',
            'picture'  => 'sometimes|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $pet = Pet::findOrFail($id);
        if ($pet->user_id !== $user->id) {
            return response()->json(['message' => 'You are not authorized to update this pet.'], 403);
        }

        if ($request->hasFile('picture')) {
            if ($pet->picture) {
                Storage::disk('public')->delete($pet->picture);
            }
            $pet->picture = $request->file('picture')->store('pet_pictures', 'public');
        }

        $pet->fill($request->only(['pet_name', 'specie', 'breed', 'sex', 'age', 'location']));
        $pet->updated_at = Carbon::now();
        $pet->save();

        // refresh cache
        Cache::forget("user_pets_{$user->id}");

        return response()->json(['message' => "Pet {$pet->pet_id} updated successfully.", 'pet' => $pet]);
    }

    // get all pets for authenticated user
    public function getUserPets()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $pets = Cache::remember("user_pets_{$user->id}", now()->addMinutes(10), function () use ($user) {
            return Pet::where('user_id', $user->id)
                ->orderBy('created_at', 'asc')
                ->get();
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
            return Pet::find($id);
        });

        if (!$pet) {
            return response()->json(['message' => 'Pet not found.'], 404);
        }

        if ($pet->user_id !== $user->id) {
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

            if ($pet->user_id !== $user->id) {
                return response()->json(['message' => 'You are not authorized to delete this pet.'], 403);
            }

            if ($pet->picture) {
                Storage::disk('public')->delete($pet->picture);
            }

            $pet->delete();

            // clear caches
            Cache::forget("user_pets_{$user->id}");
            Cache::forget("pet_{$id}");

            return response()->json(['message' => 'Pet deleted successfully.'], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while deleting the pet.',
                'error'   => $th->getMessage()
            ], 500);
        }
    }
}
