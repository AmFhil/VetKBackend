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
                'rateable_id' => 'required|integer',
                'rateable_type' => 'required|string|in:App\Models\VeterinaryDoctor,App\Models\VeterinaryParaprofessional,App\Models\VeterinaryClinic,App\Models\Store,App\Models\Product',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
            ]);

            // validate the rateable entity
            $rateable = null;

            switch ($validated['rateable_type']){
                case 'App\Models\VeterinaryDoctor':
                    $rateable = \App\Models\VeterinaryDoctor::find($validated['rateable_id']);
                    break;
                case 'App\Models\VeterinaryParaprofessional':
                    $rateable = \App\Models\VeterinaryParaprofessional::find($validated['rateable_id']);
                    break;
                case 'App\Models\VeterinaryClinic':
                    $rateable = \App\Models\VeterinaryClinic::find($validated['rateable_id']);
                    break;
                case 'App\Models\Store':
                    $rateable = \App\Models\Store::find($validated['rateable_id']);
                    break;
                case 'App\Models\Product':
                    $rateable = \App\Models\Products::find($validated['rateable_id']);
                    break;
                default:
                    return response()->json(['message' => 'Invalid rateable type.'], 400);
            };

            if (!$rateable) {
                return response()->json(['message' => 'Rateable entity not found.'], 404);
            }

            // check if user is rating themselves
            if (isset($rateable->user_id) && $rateable->user_id === $user->id) {
            return response()->json(['message' => 'You cannot rate yourself.'], 400);
        }

            // check if user has already rated this user



            $existingRate = Rate::where('user_id', $user->id)
                ->where('rateable_id', $validated['rateable_id'])
                ->where('rateable_type', $validated['rateable_type'])
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
                'rateable_id' => $validated['rateable_id'],
                'rateable_type' => $validated['rateable_type'],
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]);

            // return response
            return response()->json(['message' => 'Entity rated successfully.', 'rate' => $rate], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
