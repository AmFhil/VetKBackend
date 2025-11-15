<?php

namespace App\Http\Controllers;

use App\Models\Rate;
use Illuminate\Http\Request;

class RateController extends Controller
{
    public function rateUser(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $validated = $request->validate([
                'rateable_id' => 'required|integer',
                'rateable_type' => 'required|string|in:App\Models\VeterinaryDoctor,App\Models\VeterinaryParaprofessional,App\Models\VeterinaryClinic,App\Models\Store,App\Models\Products',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
            ]);

            // Find the rateable entity
            $rateable = $validated['rateable_type']::find($validated['rateable_id']);
            if (!$rateable) {
                return response()->json(['message' => 'Rateable entity not found.'], 404);
            }

            // Prevent self-rating
            if (isset($rateable->user_id) && $rateable->user_id === $user->id) {
                return response()->json(['message' => 'You cannot rate yourself.'], 400);
            }

            // Check for existing rating
            $existingRate = Rate::where('user_id', $user->id)
                ->where('rateable_id', $validated['rateable_id'])
                ->where('rateable_type', $validated['rateable_type'])
                ->first();

            if ($existingRate) {
                $existingRate->update([
                    'rating' => $validated['rating'],
                    'comment' => $validated['comment'] ?? $existingRate->comment,
                ]);

                // Log activity
                $user->logActivity(
                    action: 'update_rating',
                    title: 'Rating Updated',
                    detail: "You updated your rating for {$this->prettyType($validated['rateable_type'])}.",
                    meta: [
                        'rating' => $validated['rating'],
                        'rateable_type' => $this->prettyType($validated['rateable_type']),
                    ]
                );

                // Notify entity owner (if applicable)
                if (isset($rateable->user_id)) {
                    $rateable->user->sendNotification(
                        type: 'rating',
                        title: 'Rating Updated',
                        content: "Your {$this->prettyType($validated['rateable_type'])} just got its rating updated.",
                        meta: [
                            'rating' => $validated['rating'],
                            'rateable_type' => $this->prettyType($validated['rateable_type']),
                        ]
                    );
                }

                return response()->json([
                    'message' => 'User rating updated successfully.',
                    'rate' => $existingRate
                ], 200);
            }

            // Create new rating
            $rate = Rate::create([
                'user_id' => $user->id,
                'rateable_id' => $validated['rateable_id'],
                'rateable_type' => $validated['rateable_type'],
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]);

            // Log activity
            $user->logActivity(
                action: 'create_rating',
                title: 'New Rating',
                detail: "You rated {$this->prettyType($validated['rateable_type'])} with {$validated['rating']} stars.",
                meta: [
                    'rating' => $validated['rating'],
                    'rateable_type' => $this->prettyType($validated['rateable_type']),
                ]
            );

            // Notify entity owner (if applicable)
            if (isset($rateable->user_id)) {
                $rateable->user->sendNotification(
                    type: 'rating',
                    title: 'New Rating',
                    content: "Your {$this->prettyType($validated['rateable_type'])} just received a new {$validated['rating']}-star rating.",
                    meta: [
                        'rating' => $validated['rating'],
                        'rateable_type' => $this->prettyType($validated['rateable_type']),
                    ]
                );
            }

            return response()->json([
                'message' => 'Entity rated successfully.',
                'rate' => $rate
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    private function prettyType(string $type): string
    {
        return match ($type) {
            'App\Models\VeterinaryDoctor' => 'Veterinary Doctor',
            'App\Models\VeterinaryParaprofessional' => 'Veterinary Paraprofessional',
            'App\Models\VeterinaryClinic' => 'Veterinary Clinic',
            'App\Models\Store' => 'Store',
            'App\Models\Products' => 'Product',
            default => 'Entity',
        };
    }
}
