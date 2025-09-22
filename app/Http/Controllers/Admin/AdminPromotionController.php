<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdsPromotion;
use App\Models\PromotionPlan;
use App\Models\User;
use App\Models\UserPromotionPlan;
use App\Traits\BroadcastsAdminEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        if (!$admin || $admin->permission_level !== 1) return response()->json(['message' => "Unauthorized"], 403);

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
            'Promotion Created',
            "Added Promotion Plan",
            "Admin {$admin->first_name} added new Promotion Plan '{$promotion->title}'.",
            ['promotion_id' => $promotion->id, 'staff_id' => $admin->staff_id]
        );

        return response()->json(['message' => 'Promotion created successfully', 'data' => $promotion], 200);
    }

    // List subscriptions
    public function listUserPromotions()
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $promotions = \App\Models\UserPromotionPlan::with(['user', 'promotionPlan'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($promotions);
    }

    public function expirePromotion($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $promo = \App\Models\UserPromotionPlan::findOrFail($id);
        $promo->update([
            'status' => 'expired',
            'end_date' => now()
        ]);

        $this->createAdminActivity(
            $admin->id,
            'expired',
            "Promotion Plan Expired",
            "Admin {$admin->first_name} {$admin->last_name} expired promotion plan '{$promo->promotion_title}' for User {$promo->user_id}.",
            ['promotion_id' => $id]
        );

        $this->createAdminNotification(
            $admin->id,
            "Promotion Expired",
            'promotion',
            "You expired promotion plan #{$id}.",
            ['promotion_id' => $id]
        );

        return response()->json(['message' => 'Promotion plan expired successfully']);
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
        if (!$admin || $admin->permission_level !== 1) return response()->json(['message' => "Unauthorized"], 403);

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
            'Promotion Plan',
            "Updated Promotion Updated",
            "Admin {$admin->first_name} updated a Promotion Plan '{$promotion->title}'.",
            ['promotion_id' => $promotion->id, 'staff_id' => $admin->staff_id]
        );

        return response()->json(['message' => 'Promotion updated successfully', 'data' => $promotion], 200);
    }

    // Delete subscription
    public function deletePromotion($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => "Unauthorized"], 403);

        // check is user is using this promotion
        $check = UserPromotionPlan::where('promotion_id', $id)->first();

        if ($check) {
            return (['code' => 400, 'detail' => 'Users are attached to this plan, you can not delete the plan']);
        } else {
            $promotion = PromotionPlan::findOrFail($id);
            $promotion->delete();


            $this->createAdminActivity(
                $admin->id,
                'deleted',
                "Promotion Deleted",
                "Admin {$admin->first_name} deleted promotion '{$promotion->title}'.",
                ['promotion_id' => $promotion->id, 'staff_id' => $admin->staff_id]
            );

            return response()->json(['message' => 'Promotion deleted successfully']);
        }
    }

    public function getPromotionApprovals()
    {
        $promotions = PromotionPlan::with('user', 'user.profile', 'user.activeRole', 'user.activeRoleData', 'ratings')
            ->latest()
            ->paginate(10);

        $promotions->getCollection()->transform(function ($promotion) {
            $promotion->status = $promotion->end_date && Carbon::parse($promotion->end_date)->isFuture()
                ? 'active'
                : 'expired';
            return $promotion;
        });

        return response()->json($promotions);
    }

    public function getUsersByPromotion($type)
    {
        $users = User::whereHas('promotions.plan', function ($q) use ($type) {
            $q->where('promotion_title', $type);
        })->with('promotions.plan')->get();

        $users->each(function ($user) {
            $user->promotions->each(function ($promotion) {
                $promotion->status = $promotion->end_date && Carbon::parse($promotion->end_date)->isFuture()
                    ? 'active'
                    : 'expired';
            });
        });

        return response()->json([
            'type'  => $type,
            'count' => $users->count(),
            'users' => $users
        ]);
    }

    //suspend a user promotion
    public function activatePromotion(Request $request)
    {
        $request->validate([
            'user_id' => 'required|in:users_registration',
            'role' => 'required|string'
        ]);
        try {
            $success = AdsPromotion::where([['user_id', $request->user_id], ['role', $request->user_role]])->update([
                'suspended' => 0
            ]);
            if ($success) {
                return ['detail' => 'You successfully activated a promotion'];

                $user->sendNotification(
                    'ad_promotion activated',
                    'Ad Promoted Activated',
                    "Your advertisement <strong>{$ad->product_title}</strong> has been activated and is now visible to buyers. Good Luck!",
                    ['ad_id' => $ad->id]
                );
            } else {
                return (['code' => 400, 'detail' => 'Something went wrong! Try again']);
            }
        } catch (\Throwable $th) {
            return (['code' => 400, 'detail' => $th->errorInfo[2]]);
        }
    }

    //activate a user promotion
    public function deactivatePromotion(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|in:users_registration',
                'role' => 'required|string'
            ]);
            $success = AdsPromotion::where([['user_id', $request->user_id], ['role', $request->user_role]])->update([
                'suspended' => 1
            ]);
            if ($success) {
                return ['detail' => 'You successfully deactivated a promotion'];

                $user = User::where('id', $request->user_id)->first();

                $user->sendNotification(
                    'ad_promotion de-activated',
                    'Ad Promoted De-activated',
                    "Your advertisement <strong>{$ad->product_title}</strong> has been de-activated and is not visible to buyers. Contact admin if mistaken!",
                    ['ad_id' => $ad->id]
                );
            } else {
                return (['code' => 400, 'detail' => 'Something went wrong! Try again']);
            }
        } catch (\Throwable $th) {
            return (['code' => 400, 'detail' => $th->errorInfo[2]]);
        }
    }
}
