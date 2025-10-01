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
     * Promote a product (auto-purchase a promotion plan for this product).
     */
    public function promoteAd(Request $request)
    {
        $user = auth('user')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 403);

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'promotion_plan_id' => 'required|exists:promotion_plan,id',
        ]);

        // check how many active promotion user has for this promotion plan has the number of products limits
        $activePromotionsCount = AdsPromotion::where('user_id', $user->id)
            ->where('promotion_plan_id', $validated['promotion_plan_id'])
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->count();

        // check no_of_products limit
        $planDetails = PromotionPlan::find($validated['promotion_plan_id']);
        if ($activePromotionsCount >= $planDetails->no_of_products) {
            return response()->json(['message' => 'You have reached the maximum number of active promotions for this plan.'], 400);
        }


        $plan = PromotionPlan::active()->findOrFail($validated['promotion_plan_id']);

        // Calculate start/end date
        $startDate = Carbon::now();
        $endDate = $plan->date_option === 'Days'
            ? $startDate->copy()->addDays($plan->duration)
            : $startDate->copy()->addMonths($plan->duration);

        $promotion = AdsPromotion::create([
            'user_id' => $user->id,
            'product_id' => $validated['product_id'],
            'promotion_plan_id' => $plan->id,
            'active_role' => optional($user->activeRole())->name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active',
        ]);

        // Log + Notify
        $user->logActivity(
            'ad_promotion',
            "Promoted Product #{$promotion->product_id}",
            "You purchased '{$plan->title}' plan for product ID {$promotion->product_id}. Active until {$endDate->toFormattedDateString()}",
            ['promotion_id' => $promotion->id, 'plan_id' => $plan->id]
        );

        $user->sendNotification(
            'ad_promoted',
            'Product Promoted',
            "Your product (ID {$promotion->product_id}) has been promoted with the <strong>{$plan->title}</strong> plan until {$endDate->toFormattedDateString()}.",
            ['promotion_id' => $promotion->id, 'plan_id' => $plan->id]
        );

        Cache::forget("user_{$user->id}_adsPromotions");

        return response()->json(['message' => 'Promotion created successfully', 'promotion' => $promotion], 201);
    }

    /**
     * Cancel promotion
     */
    public function cancelPromotion($id)
    {
        $user = Auth::user();
        $promotion = AdsPromotion::findOrFail($id);

        if ($promotion->user_id !== $user->id) {
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

        Cache::forget("user_{$user->id}_adsPromotions");

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
