<?php

namespace App\Http\Controllers;

use App\Models\PromotionPlan;
use App\Models\UserPromotionPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserPromotionPlanController extends Controller
{
    //
    /**
     * Get all plans user has purchased
     */
    public function myPlans(Request $request)
    {
        $user = $request->user();

        $plans = Cache::remember(
            "user_{$user->id}_promotion_plans",
            now()->addMinutes(5),
            fn() => UserPromotionPlan::with('promotionPlan')
                ->where('user_id', $user->id)
                ->latest()
                ->get()
        );

        return response()->json($plans);
    }

    /**
     * Buy a promotion plan (dummy – no Paystack yet)
     */
    public function buyPlan(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'promotion_id' => 'required|exists:promotion_plan,id',
        ]);

        $plan = PromotionPlan::findOrFail($validated['promotion_id']);

        // calculate end date
        $startDate = Carbon::now();
        $endDate = $plan->date_option === 'Days'
            ? $startDate->copy()->addDays($plan->duration)
            : $startDate->copy()->addMonths($plan->duration);

        // create new user promotion plan
        $userPlan = UserPromotionPlan::create([
            'user_id'        => $user->id,
            'role'           => $user->activeRole?->name ?? 'user',
            'promotion_title' => $plan->title,
            'promotion_id'   => $plan->id,
            'price'          => $plan->price,
            'no_of_products' => $plan->no_of_products,
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'status'         => 'active',
        ]);

        // clear cache for this user
        Cache::forget("user_{$user->id}_promotion_plans");

        // user logs activity + notification
        $user->logActivity(
            'buy_promotion_plan',
            'Promotion Plan Purchased',
            "You purchased the '{$plan->title}' plan.",
            [
                'plan_id' => $plan->id,
                'price' => $plan->price,
                'duration' => $plan->duration,
                'date_option' => $plan->date_option,
                'end_date' => $endDate
            ]
        );

        $user->sendNotification(
            'promotion_plan',
            'Promotion Plan Activated',
            "Your '{$plan->title}' promotion plan is now active until {$endDate->toFormattedDateString()}",
            ['promotion_id' => $plan->id, 'user_plan_id' => $userPlan->id]
        );

        return response()->json([
            'message'   => 'Promotion plan purchased successfully',
            'user_plan' => $userPlan,
        ], 201);
    }

    /**
     * Expire a user’s promotion plan manually (admin use later)
     */
    public function expirePlan(Request $request, $id)
    {
        $userPlan = UserPromotionPlan::findOrFail($id);

        $userPlan->update([
            'status'    => 'expired',
            'end_date'  => now(),
        ]);

        Cache::forget("user_{$userPlan->user_id}_promotion_plans");

        return response()->json(['message' => 'Promotion plan expired successfully']);
    }
}
