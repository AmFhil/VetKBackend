<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromotionPlan;
use App\Traits\BroadcastsAdminEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminPromotionController extends Controller
{
    use BroadcastsAdminEvents;
    //
     private function generatePromotionCode()
    {
        $promotionCount = PromotionPlan::all()->count();
        $randomNumber = mt_rand(100000, 999999);
        $letter = chr(97 + $promotionCount);

        return "PP{$randomNumber}{$letter}";
    }

    public function addPromotion(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => "Unauthorized"], 403);

        $validated = $request->validate([
            'promotion_title' => 'required|string|max:50|unique:promotion_plan,title',
            'currency' => 'required|string|max:10',
            'price' => 'required|numeric|min:0',
            'vat' => 'nullable|string|max:10',
            'date_option' => 'required|in:Days,Months',
            'duration' => 'required|numeric|min:1',
            'no_of_products' => 'nullable|integer',
            'status' => 'required|string|in:active,inactive',
        ]);

        $promotion_code = $this->generatePromotionCode();

        $promotion = PromotionPlan::create([
            'promotion_title' => $validated['promotion_title'],
            'promotion_code' => $promotion_code,
            'currency' => $validated['currency'],
            'price' => $validated['price'],
            'vat' => $validated['vat'],
            'date_option' => $validated['date_option'],
            'duration' => $validated['duration'],
            'no_of_products' => $validated['no_of_products'],
            'status' => $validated['status'],
            'created_at' => Carbon::now()
        ]);

        $this->createAdminActivity(
            $admin->id,
            'created',
            "Promotion Created",
            "Admin {$admin->first_name} created promotion '{$promotion->title}'.",
            ['promotion_id' => $promotion->id]
        );

        return response()->json(['message' => 'Promotion created successfully', 'data' => $promotion]);
    }

    // List subscriptions
    public function listPromotion()
    {
        $promotion = PromotionPlan::all();
        return response()->json($promotion);
    }

    // View single subscription
    public function viewPromotion($id)
    {
        $promotion = PromotionPlan::findOrFail($id);
        return response()->json($promotion);
    }

    // Update subscription
    public function updatePromotion(Request $request, $id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => "Unauthorized"], 403);

        $promotion = PromotionPlan::findOrFail($id);

        $validated = $request->validate([
            'promotion_title' => 'required|string|max:50|unique:promotion_plan,title',
            'currency' => 'required|string|max:10',
            'price' => 'required|numeric|min:0',
            'vat' => 'nullable|string|max:10',
            'date_option' => 'required|in:Days,Months',
            'duration' => 'required|numeric|min:1',
            'no_of_products' => 'nullable|integer',
            'status' => 'required|string|in:active,inactive',
        ]);

        $promotion->update([
            'promotion_title' => $validated['promotion_title'],
            'currency' => $validated['currency'],
            'price' => $validated['price'],
            'vat' => $validated['vat'],
            'date_option' => $validated['date_option'],
            'duration' => $validated['duration'],
            'no_of_products' => $validated['no_of_products'],
            'status' => $validated['status'],
            'updated_at' => Carbon::now()
        ]);

        $this->createAdminActivity(
            $admin->id,
            'updated',
            "Promotion Updated",
            "Admin {$admin->first_name} updated promotion '{$promotion->title}'.",
            ['promotion_id' => $promotion->id]
        );

        return response()->json(['message' => 'Promotion updated successfully', 'data' => $promotion]);
    }

    // Delete subscription
    public function deletePromotion($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => "Unauthorized"], 403);

        $promotion = PromotionPlan::findOrFail($id);
        $promotion->delete();

        $this->createAdminActivity(
            $admin->id,
            'deleted',
            "Promotion Deleted",
            "Admin {$admin->first_name} deleted promotion '{$promotion->title}'.",
            ['promotion_id' => $promotion->id]
        );

        return response()->json(['message' => 'Promotion deleted successfully']);
    }
}
