<?php

namespace App\Http\Controllers;

use App\Models\AdsPromotion;
use App\Models\UserPromotionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AdsPromotionController extends Controller
{
    // Promote new Ad
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
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
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

        $ad = AdsPromotion::create([
            'user_id' => $user->id,
            'store_id' => $request->store_id,
            'role' => optional($user->activeRole())->name,
            'product_title' => $request->product_title,
            'product_category' => $request->product_category,
            'location' => $request->location,
            'product_description' => $request->product_description,
            'price' => $request->price,
            'available_unit' => $request->available_unit,
            'images' => $storedImages,
            'tags' => $request->tags ?? [],
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'availability' => true,
            'user_promotion_plan_id' => $userPlan->id,
        ]);

        // LOG ACTIVITY (action, title, detail, meta)
        $user->logActivity(
            'ad_promotion',
            "Promoted Ad: {$ad->product_title}",
            "You published an advertisement titled '{$ad->product_title}' in category '{$ad->product_category}' at '{$ad->location}'. Price: {$ad->price}. Units: {$ad->available_unit}.",
            [
                'ad_id' => $ad->id,
                'product_title' => $ad->product_title,
                'product_category' => $ad->product_category,
                'store_id' => $ad->store_id,
                'user_promotion_plan_id' => $userPlan->id,
            ]
        );

        // SEND NOTIFICATION (type, title, content, meta)
        $user->sendNotification(
            'ad_promoted',
            'Ad Promoted Successfully',
            "Your advertisement <strong>{$ad->product_title}</strong> has been promoted and is now visible to buyers. Good luck!",
            ['ad_id' => $ad->id]
        );

        return response()->json(['message' => 'Ad promoted successfully', 'ad' => $ad], 201);
    }

    // Update Ad
    public function updateAd(Request $request, $id)
    {
        $user = Auth::user();
        $ad = AdsPromotion::findOrFail($id);

        if ($ad->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'product_title' => 'sometimes|string|max:255',
            'product_category' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:255',
            'product_description' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'available_unit' => 'sometimes|numeric|min:0',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'longitude' => 'sometimes|numeric',
            'latitude' => 'sometimes|numeric',
        ]);

        // Handle image replacement
        if ($request->hasFile('images')) {
            if (!empty($ad->images)) {
                foreach ($ad->images as $oldImage) {
                    Storage::disk('public')->delete($oldImage);
                }
            }
            $newImages = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('ads_promotion_images', 'public');
                $newImages[] = $path;
            }
            $ad->images = $newImages;
        }

        $oldTitle = $ad->product_title;

        $ad->fill($request->only([
            'product_title',
            'product_category',
            'location',
            'product_description',
            'price',
            'available_unit',
            'tags',
            'longitude',
            'latitude',
        ]));
        $ad->save();

        // LOG ACTIVITY
        $user->logActivity(
            'ad_update',
            "Updated Ad: {$ad->product_title}",
            "You updated your ad '{$oldTitle}'. Current title: '{$ad->product_title}', category: '{$ad->product_category}', price: {$ad->price}, units: {$ad->available_unit}.",
            ['ad_id' => $ad->id]
        );

        // SEND NOTIFICATION
        $user->sendNotification(
            'ad_updated',
            'Ad Updated',
            "Your ad <strong>{$ad->product_title}</strong> has been updated successfully.",
            ['ad_id' => $ad->id]
        );

        return response()->json(['message' => 'Ad updated successfully', 'ad' => $ad], 200);
    }

    // Delete Ad
    public function deleteAd($id)
    {
        $user = Auth::user();
        $ad = AdsPromotion::findOrFail($id);

        if ($ad->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!empty($ad->images)) {
            foreach ($ad->images as $oldImage) {
                Storage::disk('public')->delete($oldImage);
            }
        }

        $adTitle = $ad->product_title;
        $adId = $ad->id;
        $ad->delete();

        // LOG ACTIVITY
        $user->logActivity(
            'ad_delete',
            "Deleted Ad: {$adTitle}",
            "You removed the advertisement titled '{$adTitle}'. It is no longer visible on the platform.",
            ['ad_id' => $adId]
        );

        // SEND NOTIFICATION
        $user->sendNotification(
            'ad_deleted',
            'Ad Deleted',
            "Your advertisement <strong>{$adTitle}</strong> has been removed from the platform.",
            ['ad_id' => $adId]
        );

        return response()->json(['message' => 'Ad deleted successfully'], 200);
    }

    // Toggle availability
    public function toggleAvailability($id)
    {
        $user = Auth::user();
        $ad = AdsPromotion::findOrFail($id);

        if ($ad->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $ad->availability = !$ad->availability;


        $ad->save();

        $status = $ad->availability ? 'available' : 'unavailable';

        // LOG ACTIVITY
        $user->logActivity(
            'ad_toggle',
            "Toggled availability for '{$ad->product_title}'",
            "You set the advertisement '{$ad->product_title}' to '{$status}'. Buyers will " . ($ad->availability ? "see" : "not see") . " this listing.",
            ['ad_id' => $ad->id, 'availability' => $ad->availability]
        );

        // SEND NOTIFICATION
        $user->sendNotification(
            'ad_availability_changed',
            'Ad Availability Changed',
            "Your ad <strong>{$ad->product_title}</strong> is now <strong>{$status}</strong>.",
            ['ad_id' => $ad->id, 'availability' => $ad->availability]
        );

        return response()->json(['message' => "Ad marked as {$status}", 'ad' => $ad], 200);
    }
}
