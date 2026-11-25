<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class PaystackSubscriptionController extends Controller
{
    /**
     * Initialize Paystack payment for a subscription
     */
    public function initialize(Request $request)
    {
        $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
        ]);

        $user = Auth::user();
        $plan = Subscription::findOrFail($request->subscription_id);


        // For USD, use amount * 100.
        $planAmount = $plan->price * 100;

        // add vat
        $vat = ($plan->vat / 100) * $planAmount;
        $amount = intval($planAmount + $vat);

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email,
                'amount' => $amount,
                'currency' => config('services.paystack.currency', 'NGN'),
                'callback_url' => config('services.paystack.callback_subscription_url'),
                'metadata' => [
                    'type' => 'subscription',
                    'user_id' => $user->id,
                    'subscription_id' => $plan->id,
                    'plan_title' => $plan->subscription_title,
                    'price' => $plan->price,
                    'vat' => $plan->vat
                ],
            ]);

        if (!$response->successful() || !$response['status']) {
            return response()->json([
                'success' => false,
                'message' => $response['message'] ?? 'Failed to initialize payment',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'authorization_url' => $response['data']['authorization_url'],
            'reference' => $response['data']['reference'],
        ]);
    }

    /**
     * Verify Paystack transaction and activate subscription
     */
    public function verify(Request $request, SubscriptionService $service)
    {
        $reference = $request->query('reference');

        if (!$reference) {
            return response()->json([
                'success' => false,
                'message' => 'Reference not provided',
            ], 400);
        }

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        $data = $response->json();

        if (!$response->successful() || !$data['status']) {
            return response()->json([
                'success' => false,
                'message' => $data['message'] ?? 'Failed to verify transaction',
            ], 400);
        }

        $payment = $data['data'];

        // Ensure successful transaction
        if ($payment['status'] !== 'success') {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not successful',
            ], 400);
        }

        $userId = $payment['metadata']['user_id'];
        $subscriptionId = $payment['metadata']['subscription_id'];

        // Authenticate user context (for queue-safe logic)
        if (Auth::id() !== $userId) {
            Auth::loginUsingId($userId);
        }

        // Create subscription + receipt
        $subscription = $service->subscribe(
            $subscriptionId,
            'paystack',
            [
                'transaction_id' => $payment['id'], // Paystack’s internal txn ID
                'reference_number' => $payment['reference'], // public ref
                'amount' => $payment['amount'] / 100,
                'currency' => $payment['currency'],
                'raw_response' => $payment,
            ]
        );

        $url = env('FRONTEND_URL');
        //return Redirect::to($url . "/dashboard/orders");
        // return response()->json([
        //     'success' => true,
        //     'message' => 'Subscription payment verified and activated',
        //     'subscription' => $subscription->load(['subscription', 'receipts']),
        // ]);
    }
}
