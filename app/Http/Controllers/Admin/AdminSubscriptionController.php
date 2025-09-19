<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subscription;

class AdminSubscriptionController extends Controller
{
    // Add a subscription
    public function addSubscription(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => "Unauthorized"], 403);

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

        return response()->json(['message' => 'Subscription created successfully', 'data' => $subscription]);
    }

    // List subscriptions
    public function listSubscriptions()
    {
        $subscriptions = Subscription::all();
        return response()->json($subscriptions);
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
        if (!$admin) return response()->json(['message' => "Unauthorized"], 403);

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

        return response()->json(['message' => 'Subscription updated successfully', 'data' => $subscription]);
    }

    // Delete subscription
    public function deleteSubscription($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => "Unauthorized"], 403);

        $subscription = Subscription::findOrFail($id);
        $subscription->delete();

        return response()->json(['message' => 'Subscription deleted successfully']);
    }
}
