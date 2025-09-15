<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileController extends Controller
{
    //
    public function getMyProfile(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $roleRelation = $user->activeRoleModel();

        if (!$roleRelation) {
            return response()->json(['message' => 'No active role found'], 404);
        }

        $profile = $roleRelation
            ->with([
                'ratings:id,rateable_id,rating,comment',
                'user.profile:id,user_id,bio,profile_image,cover_page_image'
            ])
            ->first();

        if (!$profile) {
            return response()->json(['message' => 'No profile found for this role'], 404);
        }

        $profile->average_rating = $profile->averageRating();

        return response()->json([
            'role' => $user->activeRole?->name,
            'profile' => $profile
        ], 200);
    }
}
