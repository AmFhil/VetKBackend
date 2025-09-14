<?php

namespace App\Http\Controllers;

use App\Models\AdsPromotion;
use App\Models\UserPromotionPlan;
use Illuminate\Http\Request;

class AdsPromotionController extends Controller
{
    //
    public function promoteAd(Request $request)
    {
        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $request->validate([
            'store_id' => 'nullable|exists:store,id',
            'product_title' => 'required|string|max:255',
            'product_category' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'product_description' => 'nullable|string',
            'price' => 'required|numeric',
            'available_unit' => 'required|numeric|min:0',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // multiple image uploads
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50', // each tag max length 50
            'longitude' => 'sometimes|numeric',
            'latitude' => 'sometimes|numeric',
        ]);

        // get active plan
        $userPlan = UserPromotionPlan::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->first();

        if (!$userPlan) {
            return response()->json(['message' => 'You have no active promotion plan'], 403);
        }

        // check product limit
        $adCount = AdsPromotion::where('user_promotion_plan_id', $userPlan->id)->count();
        if ($adCount >= $userPlan->no_of_products) {
            return response()->json(['message' => 'Product promotion limit reached'], 403);
        }

        // handle multiple image upload
        $storedImages = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('ads_promotion_images', 'public');
                $storedImages[] = $path;
            }
        }

        // create ad promotion
        $ad = AdsPromotion::create([
            'user_id' => $user->id,
            'store_id' => $request->store_id,
            'role' => $user->activeRole()->name,
            'product_title' => $request->product_title,
            'product_category' => $request->product_category,
            'location' => $request->location,
            'product_description' => $request->product_description,
            'price' => $request->price,
            'available_unit' => $request->available_unit,
            'images' => $storedImages, // stored as JSON
            'tags' => $request->tags ?? [], // store tags as JSON array
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'availability' => true,
            'user_promotion_plan_id' => $userPlan->id,
        ]);

        return response()->json(['message' => 'Ad promoted successfully', 'ad' => $ad]);
    }
}
