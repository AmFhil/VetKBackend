<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscriptions;
use App\Traits\BroadcastsAdminEvents;
use Carbon\Carbon;

class AdminSubscriptionController extends Controller
{
    use BroadcastsAdminEvents;

    /**
     * Add a new subscription plan
     */
    public function addSubscription(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => "Unauthorized"], 403);
        }

        if((int) $admin->permission_level !== 1) return response()->json(['message' => "Unauthorized"], 403);

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
            "Admin {$admin->first_name} added new Subscription Plan '{$subscription->subscription_title}'.",
            ['subscription_id' => $subscription->id, 'staff_id' => $admin->staff_id]
        );

        return response()->json(['message' => 'Subscription created successfully', 'data' => $subscription], 201);
    }

    /**
     * List all user subscriptions (with cache)
     */
    public function listUserSubscriptions()
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $page = (int) request()->get('page', 1);
        $perPage = 20;


        $subscriptions =  UserSubscriptions::getUserSubscriptionsCached($page, $perPage);


        return response()->json($subscriptions);
    }

    /**
     * Deactivate a user subscription
     */
    public function deactivateSubscription($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $userSub = UserSubscriptions::findOrFail($id);
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

    /**
     *  View single subscription plan
     */
    public function viewSubscription($id)
    {
        $subscription = Subscription::findOrFail($id);
        return response()->json($subscription);
    }

    /**
     * Update subscription plan
     */
    public function updateSubscription(Request $request, $id)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => "Unauthorized"], 403);
        }

        if((int) $admin->permission_level !== 1) return response()->json(['message' => "Unauthorized"], 403);

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
            "Admin {$admin->first_name} updated Subscription Plan '{$subscription->subscription_title}'.",
            ['subscription_id' => $subscription->id, 'staff_id' => $admin->staff_id]
        );

        return response()->json(['message' => 'Subscription updated successfully', 'data' => $subscription], 200);
    }

    /**
     * Delete subscription plan (only if no users tied)
     */
    public function deleteSubscription($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => "Unauthorized"], 403);

        $check = UserSubscriptions::where('subscription_id', $id)->first();

        if ($check) {
            return response()->json([
                'code' => 400,
                'detail' => 'Users are attached to this plan, you cannot delete the plan'
            ], 400);
        }

        $subscription = Subscription::findOrFail($id);
        $subscriptionTitle = $subscription->subscription_title;
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

    /**
     * Get all user subscriptions with approval status
     */
    public function getSubscriptionApprovals()
    {
        $page = (int) request()->get('page', 1);
        $perPage = 10;

        $subscriptions =  UserSubscriptions::getApprovalsCached($page, $perPage);


        $subscriptions->getCollection()->transform(function ($userSub) {
            $userSub->status = $userSub->end_date && Carbon::parse($userSub->end_date)->isFuture()
                ? 'active'
                : 'expired';
            return $userSub;
        });

        return response()->json($subscriptions);
    }

    /**
     * Get users by subscription type (title)
     */
    public function getUsersBySubscription($type)
    {
        $cacheKey = "users_by_subscription_{$type}";

        $users = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($type) {
            return User::whereHas('subscription.subscription', function ($q) use ($type) {
                $q->where('subscription_title', $type);
            })->with('subscription.subscription')->get();
        });

        $users->each(function ($user) {
            if ($user->subscription) {
                $user->subscription->status = $user->subscription->end_date &&
                    Carbon::parse($user->subscription->end_date)->isFuture()
                    ? 'active'
                    : 'expired';
            }
        });

        return response()->json([
            'type'  => $type,
            'count' => $users->count(),
            'users' => $users
        ]);
    }

    // get all subscription
    public function getAllSubscriptions()
    {
        $subscriptions = Cache::remember('all_subscriptions', now()->addMinutes(30), function () {
            return Subscription::latest()->paginate(10);
        });

        return response()->json(['message' => "success", 'subscriptions' => $subscriptions], 200);
    }
}
