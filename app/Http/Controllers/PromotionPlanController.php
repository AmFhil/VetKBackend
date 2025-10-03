<?php

namespace App\Http\Controllers;

use App\Models\PromotionPlan;
use App\Traits\BroadcastsAdminEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PromotionPlanController extends Controller
{
    use BroadcastsAdminEvents;

    // Generate a unique promotion code
    private function generatePromotionCode()
    {
        return 'PC-' . strtoupper(uniqid());
    }

    /**
     * Get all active promotion plans (user side)
     */
    public function getActivePlans()
    {
        $plans = Cache::remember('promotion_plans_active', now()->addHour(), function () {
            return PromotionPlan::active()
                ->select('id', 'promotion_code', 'title', 'currency', 'price', 'vat', 'duration', 'no_of_products', 'status')
                ->orderBy('price')
                ->get();
        });

        return response()->json(['plans' => $plans], 200);
    }

    /**
     * Get all promotion plans (admin only, paginated)
     */
    public function getAllPlans(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $page = (int) $request->get('page', 1);
        $cacheKey = "promotion_plans_all_page_{$page}";

        $plans = Cache::remember($cacheKey, now()->addMinutes(10), function () {
            return PromotionPlan::latest()->paginate(10);
        });

        return response()->json(['plans' => $plans], 200);
    }

    /**
     * Create a new promotion plan (admin only)
     */
    public function createPlan(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'currency'       => 'required|string|max:10',
            'price'          => 'required|numeric|min:0',
            'vat'            => 'nullable|numeric|min:0',
            'date_option'    => 'required|string|in:Days,Months',
            'duration'       => 'required|integer|min:1',
            'no_of_products' => 'required|integer|min:1',
            'status'         => 'required|in:active,inactive',
        ]);

        $validated['promotion_code'] = $this->generatePromotionCode();

        $plan = PromotionPlan::create($validated);

        $this->clearCaches();

        $this->createAdminActivity(
            $admin->id,
            'Created Promotion Plan',
            'Created new promotion plan: ' . $plan->title,
            'You created a new promotion plan: ' . $plan->title,
            ['plan_id' => $plan->id]
        );

        $this->createAdminNotification(
            $admin->id,
            'new_promotion_plan',
            'New Promotion Plan Created',
            'A new promotion plan (' . $plan->title . ')' . ' has been created.',
            ['plan_id' => $plan->id, 'staff_id' => $admin->staff_id]
        );

        return response()->json(['message' => 'Promotion plan created successfully', 'plan' => $plan], 201);
    }

    /**
     * Update promotion plan (admin only)
     */
    public function updatePlan(Request $request, $id)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $plan = PromotionPlan::findOrFail($id);

        $validated = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'currency'       => 'sometimes|string|max:10',
            'price'          => 'sometimes|numeric|min:0',
            'vat'            => 'nullable|numeric|min:0',
            'date_option'    => 'sometimes|string|in:Days,Months',
            'duration'       => 'sometimes|integer|min:1',
            'no_of_products' => 'sometimes|integer|min:1',
            'status'         => 'sometimes|in:active,inactive',
        ]);

        $plan->update($validated);

        $this->clearCaches();

        $this->createAdminActivity(
            $admin->id,
            'Updated To A Promotion Plan',
            'Updated promotion plan: ' . $plan->title,
            'You updated a promotion plan: ' . $plan->title,
            ['plan_id' => $plan->id]
        );

        $this->createAdminNotification(
            $admin->id,
            'update_to_a_promotion_plan',
            'Promotion Plan Updated',
            'A promotion plan (' . $plan->title . ')' . ' has been Updated.',
            ['plan_id' => $plan->id, 'staff_id' => $admin->staff_id]
        );

        return response()->json(['message' => 'Promotion plan updated successfully', 'plan' => $plan], 200);
    }

    /**
     * Delete promotion plan (admin only)
     */
    public function deletePlan($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $plan = PromotionPlan::findOrFail($id);

        // check if plan is linked to any user 
        if ($plan->adsPromotion()->exists()){
            return response()->json(['message' => 'Cannot delete plan linked to active promotions'], 400);
        }

        $plan->delete();

        $this->createAdminActivity(
            $admin->id,
            'Deleted Promotion Plan',
            'Deleted promotion plan: ' . $plan->title,
            'You deleted a promotion plan: ' . $plan->title,
            ['plan_id' => $plan->id]
        );

        $this->createAdminNotification(
            $admin->id,
            'deleted_promotion_plan',
            'Promotion Plan Deleted',
            'A promotion plan (' . $plan->title . ')' . ' has been deleted.',
            ['plan_id' => $plan->id, 'staff_id' => $admin->staff_id]
        );

        $this->clearCaches();

        return response()->json(['message' => 'Promotion plan deleted successfully'], 200);
    }

    /**
     * Helper to clear related caches
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
