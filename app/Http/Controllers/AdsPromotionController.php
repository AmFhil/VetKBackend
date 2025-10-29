<?php

namespace App\Http\Controllers;

use App\Models\AdsPromotion;
use App\Models\PromotionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AdsPromotionController extends Controller
{


    /**
     * Cancel promotion
     */
    public function cancelPromotion($id)
    {
        $user = Auth::user();
        $promotion = AdsPromotion::findOrFail($id);

        if ((int) $promotion->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $promotion->update(['status' => 'cancelled']);

        $user->logActivity(
            'ad_cancelled',
            "Cancelled promotion for product #{$promotion->product_id}",
            "You cancelled the promotion linked to product ID {$promotion->product_id}.",
            ['promotion_id' => $promotion->id]
        );

        $user->sendNotification(
            'promotion_cancelled',
            'Promotion Cancelled',
            "Your promotion for product ID {$promotion->product_id} has been cancelled.",
            ['promotion_id' => $promotion->id]
        );

        return response()->json(['message' => 'Promotion cancelled successfully'], 200);
    }

    /**
     * List all promotions for logged-in user
     */
    public function myPromotions()
    {
        $user = Auth::user();

        $promotions = Cache::remember("user_{$user->id}_adsPromotions", now()->addMinutes(30), function () use ($user) {
            return AdsPromotion::with('product', 'promotionPlan')
                ->where('user_id', $user->id)
                ->latest()
                ->get();
        });


        return response()->json($promotions);
    }
}
