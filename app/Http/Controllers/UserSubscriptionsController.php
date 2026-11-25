<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Subscription;
use App\Models\UserSubscriptions;
use App\Traits\BroadcastsAdminEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserSubscriptionsController extends Controller
{
    use BroadcastsAdminEvents;
    /**
     *Get current user’s active subscription
     */
    public function current()
    {
        $user = Auth::user();
        $subscription = $user->subscription?->load('subscription');

        return response()->json([
            'success' => true,
            'subscription' => $subscription,
        ]);
    }

    /**
     * Cancel current active subscription
     */
    public function cancel()
    {
        $user = Auth::user();
        $subscription = $user->subscription;

        if (!$subscription || !$subscription->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription to cancel',
            ], 400);
        }

        $subscription->update(['status' => 'cancelled']);

        // 🔹 use helpers
        $user->logActivity(
            action: 'subscription cancelled',
            title: 'Cancelled Subscription',
            detail: "User cancelled subscription {$subscription->subscription->subscription_title}",
            meta: [
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->subscription_id,
                'plan_title' => $subscription->subscription->subscription_title,
                'cancelled_at' => now()->toDateTimeString(),
            ]
        );

        // Send notification
        $user->sendNotification(
            type: 'subscription cancelled',
            title: 'Subscription Cancelled',
            content: "Your subscription to {$subscription->subscription->subscription_title} has been cancelled.",
            meta: [
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->subscription_id,
                'plan_title' => $subscription->subscription->subscription_title,
            ]
        );

        // send notification to all admin
        $admin = Admin::all();
        foreach ($admin as $adminUser) {
            $this->createAdminNotification(
                adminId: $adminUser->id,
                title: 'User Subscription Cancelled',
                type: 'subscription cancelled',
                content: "User {$user->name} cancelled their subscription to {$subscription->subscription->subscription_title}.",
                meta: [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'plan_id' => $subscription->subscription_id,
                    'plan_title' => $subscription->subscription->subscription_title,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled successfully',
        ]);
    }

    /**
     * Get subscription history (paginated, cached)
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 20);

        $cacheKey = "user_{$user->id}_subscriptions_page_{$page}_per_{$perPage}";

        $history = cache()->remember($cacheKey, now()->addMinutes(30), function () use ($user, $perPage) {
            return UserSubscriptions::with(['subscription', 'receipts'])
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->paginate($perPage);
        });

        return response()->json([
            'success' => true,
            'subscriptions' => $history,
        ]);
    }

    // get all subscription to show to user
    public function getAllSubscriptions()
    {
        $subscriptions = Cache::remember('all_subscriptions', now()->addMinutes(30), function () {
            return Subscription::latest()->paginate(10);
        });

        return response()->json(['message' => "success", 'subscriptions' => $subscriptions], 200);
    }
}
