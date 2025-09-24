<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserSubscriptionsController extends Controller
{
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
     *Subscribe to a new plan
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
        ]);

        $user = Auth::user();
        $plan = Subscription::findOrFail($request->subscription_id);

        $startDate = now();
        $endDate = match ($plan->date_option) {
            'days'  => $startDate->copy()->addDays($plan->duration),
            'weeks' => $startDate->copy()->addWeeks($plan->duration),
            'months' => $startDate->copy()->addMonths($plan->duration),
            'years' => $startDate->copy()->addYears($plan->duration),
            default => $startDate->copy()->addDays($plan->duration),
        };

        // expire old active subs
        UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        // create new
        $subscription = UserSubscription::create([
            'user_id'         => $user->id,
            'subscription_id' => $plan->id,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'status'          => 'active',
        ]);

        // 🔹 use helpers
        $user->logActivity(
            action: 'subscription created',
            title: 'Subscribed to Plan',
            detail: "User subscribed to {$plan->subscription_title} (ID: {$plan->id}) from {$startDate->toDateString()} to {$endDate->toDateString()}",
            meta: [
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'plan_title' => $plan->subscription_title,
                'start_date' => $startDate->toDateTimeString(),
                'end_date' => $endDate->toDateTimeString(),
                'status' => 'active',
            ]
        );

        // Send notification
        $user->sendNotification(
            type: 'subscription created',
            title: 'New Subscription Activated',
            content: "You have successfully subscribed to {$plan->subscription_title}. Your subscription is active from {$startDate->toDateString()} to {$endDate->toDateString()}.",
            meta: [
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'plan_title' => $plan->subscription_title,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated successfully',
            'subscription' => $subscription->load('subscription'),
        ], 201);
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
                detail: "User cancelled subscription {$subscription->subscription->subscription_title} (ID: {$subscription->id})",
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
            return UserSubscription::with('subscription')
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->paginate($perPage);
        });

        return response()->json([
            'success' => true,
            'subscriptions' => $history,
        ]);
    }
}
