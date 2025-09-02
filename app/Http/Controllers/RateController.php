<?php

namespace App\Http\Controllers;

use App\Models\Rate;
use Illuminate\Http\Request;

class RateController extends Controller
{
    // Implement rating functionalities here
    public function rateUser(Request $request)
    {
        try {
            // check if user is authenticated
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            // check if user have already rated this user
            // validate the request
            $validated = $request->validate([
                'rated_user_id' => 'required|exists:users_registration,id',
                'user_role' => 'required|string|in:Veterinarian,Veterinary Paraprofessional,Veterinary Clinic,Pet Owner,Livestock Farmer,Vendor,Others',
                'type' => 'required|string|in:vet,store,paraprofessional,clinic',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
            ]);

            $existingRate = Rate::where('user_id', $user->id)
                ->where('rated_user_id', $validated['rated_user_id'])
                ->where('user_role', $validated['user_role'])
                ->where('type', $validated['type'])
                ->get()
                ->first();

            if ($existingRate) {
                // update existing rate
                $rate = Rate::where('id', $existingRate->id)->update([
                    'rating' => $validated['rating'],
                    'comment' => $validated['comment'] ?? $existingRate->comment,
                ]);

                return response()->json(['message' => 'User rating updated successfully.', 'rate' => $rate], 200);
            }

            // create a new rate
            $rate = Rate::create([
                'user_id' => $user->id,
                'rated_user_id' => $validated['rated_user_id'],
                'user_role' => $validated['user_role'],
                'type' => $validated['type'],
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]);

            // return response
            return response()->json(['message' => 'User rated successfully.', 'rate' => $rate], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
