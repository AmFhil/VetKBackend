<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PromotionPlan;
use App\Models\UserPromotionPlan;
use Carbon\Carbon;

class PromotionController extends Controller
{
    public function buyPlan(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'promotion_id' => 'required|exists:promotion_plan,id'
        ]);

        $plan = PromotionPlan::findOrFail($request->promotion_id);

        // calculate end date based on duration & date_option
        $startDate = Carbon::now();
        if ($plan->date_option === 'Days') {
            $endDate = $startDate->copy()->addDays($plan->duration);
        } else {
            $endDate = $startDate->copy()->addMonths($plan->duration);
        }

        // create a user promotion plan
        $userPlan = UserPromotionPlan::create([
            'user_id' => $user->id,
            'role' => $user->activeRole()->name ?? 'user',
            'promotion_title' => $plan->title,
            'promotion_id' => $plan->id,
            'price' => $plan->price,
            'no_of_products' => $plan->no_of_products,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active'
        ]);

        return response()->json([
            'message' => 'Plan purchased successfully',
            'user_plan' => $userPlan
        ]);
    }
}

