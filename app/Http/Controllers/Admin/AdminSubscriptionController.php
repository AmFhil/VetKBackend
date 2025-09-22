<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\UserSubscription;
use App\Traits\BroadcastsAdminEvents;
use Carbon\Carbon;

class AdminSubscriptionController extends Controller
{
    use BroadcastsAdminEvents;
    // Add a subscription
    public function addSubscription(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin || $admin->permission_level !== 1) return response()->json(['message' => "Unauthorized"], 403);

        $validated = $request->validate([
            'subscription_title' => 'required|string|max:50',
            'subscription_code' => 'required|string|max:50|unique:subscriptions,subscription_code',
            'currency' => 'required|string|max:10',
            'price' => 'required|numeric|min:0',
            'vat' => 'nullable|string|max:10',
            'date_option' => 'required|in:Days,Months',
            'duration' => 'required|numeric|min:1',
            'case_record' => 'nullable|integer',
            'profile_approval' => 'required|in:No Approval Badge,Approved Badge',
            'contact_info' => 'required|boolean',
            'direct_message' => 'required|boolean',
            'feed_calculator' => 'required|boolean',
            'disease_predictor' => 'required|boolean',
            'store' => 'nullable|integer',
            'no_of_products' => 'nullable|integer',
            'customer_support' => 'required|boolean',
        ]);

        $subscription = Subscription::create($validated);

        $this->createAdminActivity(
            $admin->id,
            'subscription Plan',
            "Added Subscription Plan",
            "Admin {$admin->first_name} added new Subscription Plan '{$subscription->title}'.",
            ['subscription_id' => $subscription->id, 'staff_id' => $admin->staff_id]
        );

        return response()->json(['message' => 'Subscription created successfully', 'data' => $subscription]);
    }

    // List subscriptions
    public function listUserSubscriptions()
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $subscriptions = \App\Models\UserSubscription::with(['user', 'subscription', 'user.profile', 'user.activeRole'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($subscriptions);
    }

    public function deactivateSubscription($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $userSub = \App\Models\UserSubscription::findOrFail($id);
        $userSub->update(['status' => 'cancelled']);

        $this->createAdminActivity(
            $admin->id,
            'deactivated',
            "Subscription Deactivated",
            "Admin {$admin->first_name} {$admin->last_name} deactivated subscription for User {$userSub->user_id}.",
            ['subscription_id' => $id]
        );

        $this->createAdminNotification(
            $admin->id,
            "Subscription Deactivated",
            'subscription',
            "You deactivated subscription #{$id}.",
            ['subscription_id' => $id]
        );

        return response()->json(['message' => 'Subscription deactivated successfully']);
    }

    // View single subscription
    public function viewSubscription($id)
    {
        $subscription = Subscription::findOrFail($id);
        return response()->json($subscription);
    }

    // Update subscription
    public function updateSubscription(Request $request, $id)
    {
        $admin = auth('admin')->user();
        if (!$admin || $admin->permission_level !== 1) return response()->json(['message' => "Unauthorized"], 403);

        $subscription = Subscription::findOrFail($id);

        $validated = $request->validate([
            'subscription_title' => 'sometimes|string|max:50',
            'subscription_code' => 'sometimes|string|max:50|unique:subscriptions,subscription_code,' . $id,
            'currency' => 'sometimes|string|max:10',
            'price' => 'sometimes|numeric|min:0',
            'vat' => 'nullable|string|max:10',
            'date_option' => 'sometimes|in:Days,Months',
            'duration' => 'sometimes|numeric|min:1',
            'case_record' => 'nullable|integer',
            'profile_approval' => 'sometimes|in:No Approval Badge,Approved Badge',
            'contact_info' => 'sometimes|boolean',
            'direct_message' => 'sometimes|boolean',
            'feed_calculator' => 'sometimes|boolean',
            'disease_predictor' => 'sometimes|boolean',
            'store' => 'nullable|integer',
            'no_of_products' => 'nullable|integer',
            'customer_support' => 'sometimes|boolean',
        ]);

        $subscription->update($validated);

        $this->createAdminActivity(
            $admin->id,
            'subscription Plan',
            "Updated Subscription Plan",
            "Admin {$admin->first_name} updated a Subscription Plan '{$subscription->title}'.",
            ['subscription_id' => $subscription->id, 'staff_id' => $admin->staff_id]
        );

        return response()->json(['message' => 'Subscription updated successfully', 'data' => $subscription]);
    }

    // Delete subscription
    public function deleteSubscription($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => "Unauthorized"], 403);

        $check = UserSubscription::where('subscription_id', $id)->first();

        if ($check) {
            return (['code' => 400, 'detail' => 'Users are attached to this plan, you can not delete the plan']);
        } else {

            $subscription = Subscription::findOrFail($id);
            $subscriptionTitle = $subscription->title;
            $subscriptionId = $subscription->id;
            $subscription->delete();

            $this->createAdminActivity(
                $admin->id,
                'deleted',
                "Subscription Deleted",
                "Admin {$admin->first_name} deleted subscription '{$subscriptionTitle}'.",
                ['subscription_id' => $subscriptionId, 'staff_id' => $admin->staff_id]
            );

            return response()->json(['message' => 'Subscription deleted successfully']);
        }
    }

    public function getSubscriptionApprovals()
    {
        $subscriptions = Subscription::with('user', 'user.profile', 'user.activeRole', 'user.activeRoleData', 'ratings')
            ->latest()
            ->paginate(10);

        $subscriptions->getCollection()->transform(function ($subscription) {
            $subscription->status = $subscription->end_date && Carbon::parse($subscription->end_date)->isFuture()
                ? 'active'
                : 'expired';
            return $subscription;
        });

        return response()->json($subscriptions);
    }

    public function getUsersBySubscription($type)
    {
        $users = User::whereHas('subscriptions.plan', function ($q) use ($type) {
            $q->where('subscription_title', $type);
        })->with('subscriptions.plan')->get();

        $users->each(function ($user) {
            $user->subscriptions->each(function ($subscription) {
                $subscription->status = $subscription->end_date && Carbon::parse($subscription->end_date)->isFuture()
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
}
