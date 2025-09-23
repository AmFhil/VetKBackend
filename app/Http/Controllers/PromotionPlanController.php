<?php

namespace App\Http\Controllers;

use App\Models\PromotionPlan;
use App\Models\UserPromotionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PromotionPlanController extends Controller
{
    /**
     * 🟢 Get all active promotion plans (user side)
     */
    public function getActivePlans()
    {
        $plans = Cache::remember('promotion_plans_active', now()->addHour(), function () {
            return PromotionPlan::active()
                ->select('id', 'promotion_code', 'title', 'currency', 'price', 'vat', 'duration', 'no_of_products')
                ->orderBy('price')
                ->get();
        });

        return response()->json(['plans' => $plans], 200);
    }

    /**
     * 🟢 Get all promotion plans (admin only, paginated)
     */
    public function getAllPlans(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $page = (int) $request->get('page', 1);
        $cacheKey = "promotion_plans_all_page_{$page}";

        $plans = Cache::remember($cacheKey, now()->addMinutes(10), function () {
            return PromotionPlan::latest()->paginate(20);
        });

        return response()->json(['plans' => $plans], 200);
    }

    /**
     * 🟢 Create a new promotion plan (admin only)
     */
    public function createPlan(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $validated = $request->validate([
            'promotion_code' => 'required|string|unique:promotion_plan,promotion_code',
            'title' => 'required|string|max:255',
            'currency' => 'required|string|max:10',
            'price' => 'required|numeric|min:0',
            'vat' => 'nullable|numeric|min:0',
            'date_option' => 'nullable|string',
            'duration' => 'required|integer|min:1',
            'no_of_products' => 'required|integer|min:1',
            'status' => 'required|in:active,inactive',
        ]);

        $plan = PromotionPlan::create($validated);

        $this->clearCaches();

        return response()->json(['message' => 'Promotion plan created successfully', 'plan' => $plan], 201);
    }

    /**
     * 🟢 Update promotion plan (admin only)
     */
    public function updatePlan(Request $request, $id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $plan = PromotionPlan::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'currency' => 'sometimes|string|max:10',
            'price' => 'sometimes|numeric|min:0',
            'vat' => 'nullable|numeric|min:0',
            'date_option' => 'nullable|string',
            'duration' => 'sometimes|integer|min:1',
            'no_of_products' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $plan->update($validated);

        $this->clearCaches();

        return response()->json(['message' => 'Promotion plan updated successfully', 'plan' => $plan], 200);
    }

    /**
     * 🟢 Delete promotion plan (admin only, if no user is tied)
     */
    public function deletePlan($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $plan = PromotionPlan::findOrFail($id);

        $isTied = UserPromotionPlan::where('promotion_id', $plan->id)->exists();
        if ($isTied) {
            return response()->json(['message' => 'Cannot delete promotion plan. Users are subscribed to this plan.'], 400);
        }

        $plan->delete();

        $this->clearCaches();

        return response()->json(['message' => 'Promotion plan deleted successfully'], 200);
    }

    /**
     * 🔧 Helper to clear related caches
     */
    private function clearCaches(): void
    {
        Cache::forget('promotion_plans_active');

        // clear first 5 admin pages
        for ($i = 1; $i <= 5; $i++) {
            Cache::forget("promotion_plans_all_page_{$i}");
        }
    }
}
