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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminPromotionController extends Controller
{
    use BroadcastsAdminEvents;

    /**
     * List user promotion plans (paginated) — cached per page for 5 minutes
     */
    public function listUserPromotions(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $page = (int) $request->get('page', 1);
        $cacheKey = "admin_user_promotions_page_{$page}";

        $promotions = Cache::remember($cacheKey, now()->addMinutes(5), function () {
            return AdsPromotion::with(['user', 'user.profile', 'user.activeRole', 'promotionPlan', 'products'])
                ->orderByDesc('created_at')
                ->paginate(20);
        });

        return response()->json([$promotions, 'message' => 'User promotions retrieved successfully'], 200);
    }

    /**
     * View a single user promotion plan
     */
    public function viewUserPromotion($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $plan = AdsPromotion::with(['user', 'user.profile', 'user.activeRole', 'promotionPlan', 'products'])
            ->findOrFail($id);

        return response()->json([$plan, 'message' => 'User promotion plan retrieved successfully'], 200);
    }

    /**
     * Expire a user's promotion plan (admin)
     */
    public function expirePromotion($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $promo = AdsPromotion::findOrFail($id);

        $promo->update([
            'status' => 'expired',
            'end_date' => now(),
        ]);

        // admin activity & notification
        $this->createAdminActivity(
            $admin->id,
            'expire_promotion',
            "Promotion Plan Expired",
            "Admin {$admin->first_name} {$admin->last_name} expired promotion plan '{$promo->promotion_title}' for User {$promo->user_id}.",
            ['user_promotion_plan_id' => $promo->id, 'promotion_id' => $promo->promotion_id, 'staff_id' => $admin->staff_id ?? null]
        );

        $this->createAdminNotification(
            $admin->id,
            "Promotion Expired",
            'promotion',
            "You expired promotion plan #{$id}.",
            ['user_promotion_plan_id' => $promo->id]
        );

        return response()->json(['message' => 'Promotion plan expired successfully']);
    }

    /**
     * List promotion "approvals" — i.e. user promotion plans with computed active/expired status
     * (cached per page for 5 minutes)
     */
    public function getPromotionApprovals(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $page = (int) $request->get('page', 1);
        $cacheKey = "admin_promotion_approvals_page_{$page}";

        $paginator = Cache::remember($cacheKey, now()->addMinutes(5), function () {
            return AdsPromotion::with(['user', 'user.profile', 'user.activeRole', 'promotionPlan', 'products'])
                ->latest()
                ->paginate(10);
        });

        // ensure status is computed (active / expired)
        $paginator->getCollection()->transform(function ($item) {
            $item->computed_status = ($item->end_date && Carbon::parse($item->end_date)->isFuture()) ? 'active' : 'expired';
            return $item;
        });

        return response()->json($paginator);
    }

    /**
     * Get users by promotion plan title (e.g. "Freemium", "Weekly", "Monthly")
     * Returns grouped structure keyed by user_id with the user's promotions
     */
    public function getUsersByPromotion($title)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $cacheKey = "admin_users_by_promotion_" . md5($title);

        $plans = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($title) {
            return AdsPromotion::whereHas('promotionPlan', function ($q) use ($title) {
                // PromotionPlan uses 'title' (per your schema)
                $q->where('title', $title);
            })
                ->with(['user', 'user.profile', 'user.activeRole', 'promotionPlan', 'products'])
                ->get();
        });

        // group by user_id and prepare a response array (user + their promotions)
        $grouped = $plans->groupBy('user_id')->map(function ($group) {
            $user = $group->first()->user;
            $promotions = $group->map(function ($p) {
                $p->computed_status = ($p->end_date && Carbon::parse($p->end_date)->isFuture()) ? 'active' : 'expired';
                return $p;
            })->values();

            return [
                'user' => $user,
                'promotions' => $promotions,
            ];
        })->values();

        return response()->json([
            'title' => $title,
            'count' => $grouped->count(),
            'data' => $grouped,
        ]);
    }

    /**
     * Activate ads for a user's promotions (unsuspend)
     * Admin action: changes AdsPromotion.suspended => 0 and notifies user(s)
     */
    public function activatePromotion(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users_registration,id',
            'role' => 'required|string',
        ]);

        $userId = $validated['user_id'];
        $role = $validated['role'] ?? null;

        try {
            $query = AdsPromotion::where('user_id', $userId);
            if ($role !== null) $query->where('role', $role);

            $updated = $query->update(['status' => 'active']);

            if ($updated) {
                // notify the user (single notification per user)
                $user = User::find($userId);
                if ($user) {
                    $user->sendNotification(
                        'ad_promotion_activated',
                        'Ad Promotion Activated',
                        "Your promoted ads have been activated by admin and are now visible to buyers.",
                        ['user_id' => $userId]
                    );
                }

                // admin activity + notification
                $this->createAdminActivity(
                    $admin->id,
                    'activate_ads_promotion',
                    "Activated Ads Promotion",
                    "Admin {$admin->first_name} activated ads for user_id {$userId}" . ($role ? " (role={$role})" : ""),
                    ['user_id' => $userId, 'role' => $role, 'affected' => $updated]
                );

                $this->createAdminNotification(
                    $admin->id,
                    "Ads Activated",
                    'promotion',
                    "You activated promoted ads for user #{$userId}.",
                    ['user_id' => $userId, 'affected' => $updated]
                );

                return response()->json(['detail' => 'You successfully activated promotion(s)', 'affected' => $updated]);
            }

            return response()->json(['code' => 400, 'detail' => 'No ads updated.'], 400);
        } catch (\Throwable $th) {
            return response()->json(['code' => 500, 'detail' => $th->getMessage()], 500);
        }
    }

    /**
     * Deactivate ads for a user's promotions (suspend)
     */
    public function deactivatePromotion(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users_registration,id',
            'role' => 'nullable|string',
        ]);

        $userId = $validated['user_id'];
        $role = $validated['role'] ?? null;

        try {
            $query = AdsPromotion::where('user_id', $userId);
            if ($role !== null) $query->where('role', $role);

            $updated = $query->update(['status' => 'cancelled']);

            if ($updated) {
                $user = User::find($userId);
                if ($user) {
                    $user->sendNotification(
                        'ad_promotion_deactivated',
                        'Ad Promotion Deactivated',
                        "Your promoted ads have been de-activated by admin and are not visible to buyers. Contact support if you think this is an error.",
                        ['user_id' => $userId]
                    );
                }

                $this->createAdminActivity(
                    $admin->id,
                    'deactivate_ads_promotion',
                    "Deactivated Ads Promotion",
                    "Admin {$admin->first_name} deactivated ads for user_id {$userId}" . ($role ? " (role={$role})" : ""),
                    ['user_id' => $userId, 'role' => $role, 'affected' => $updated]
                );

                $this->createAdminNotification(
                    $admin->id,
                    "Ads Deactivated",
                    'promotion',
                    "You deactivated promoted ads for user #{$userId}.",
                    ['user_id' => $userId, 'affected' => $updated]
                );

                return response()->json(['detail' => 'You successfully deactivated promotion(s)', 'affected' => $updated]);
            }

            return response()->json(['code' => 400, 'detail' => 'No ads updated.'], 400);
        } catch (\Throwable $th) {
            return response()->json(['code' => 500, 'detail' => $th->getMessage()], 500);
        }
    }
}
