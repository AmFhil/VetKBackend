<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\AdsPromotion;
use App\Models\PromotionPlan;
use App\Services\AdsPromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class PaystackAdsPromotionController extends Controller
{
    public function initialize(Request $request)
    {
        $request->validate([
            'promotion_plan_id' => 'required|exists:promotion_plan,id',
            'product_id' => 'required|exists:products,id',
        ]);

        $user = Auth::user();

        // check how many active promotion user has for this promotion plan has the number of products limits
        $activePromotionsCount = AdsPromotion::where('user_id', $user->id)
            ->where('promotion_plan_id', $request->promotion_plan_id)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->count();

        // check no_of_products limit
        $planDetails = PromotionPlan::find($request->promotion_plan_id);
        if ($activePromotionsCount >= $planDetails->no_of_products) {
            return response()->json(['message' => 'You have reached the maximum number of active promotions for this plan.'], 400);
        }

        $plan = PromotionPlan::active()->findOrFail($request->promotion_plan_id);

        $amountUSD = $plan->price;

        // add vat
        $vat = ($plan->vat / 100) * $amountUSD;
        $totalAmount = $amountUSD + $vat;
        $amountCents = intval($totalAmount * 100);

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email,
                'amount' => $amountCents,
                'currency' => 'USD',
                'callback_url' => config('services.paystack.callback_ads_promotion_url'),
                'metadata' => [
                    'type' => 'ads_promotion',
                    'user_id' => $user->id,
                    'promotion_plan_id' => $plan->id,
                    'promotion_title' => $plan->title,
                    'product_id' => $request->product_id,
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

        $body = $response->json();

        return response()->json([
            'success' => true,
            'authorization_url' => $body['data']['authorization_url'],
            'reference' => $body['data']['reference'],
        ]);
    }

    public function verify(Request $request, AdsPromotionService $service)
    {
        $references = $request->query('references');

        if (!$references) {
            return response()->json([
                'success' => false,
                'message' => 'Reference not provided'
            ], 400);
        }

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->get("https://api.paystack.co/transaction/verify/{$references}");

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
        $productId = $payment['metadata']['product_id'];
        $promotionPlanId = $payment['metadata']['promotion_plan_id'];

        $data = [
            'user_id' => $userId,
            'product_id' => $productId,
            'promotion_plan_id' => $promotionPlanId,
            'payment_method' => 'paystack',
            'transaction_id' => $payment['id'], // Paystack’s internal txn ID
            'reference_number' => $payment['reference'], // public ref
            'amount' => $payment['amount'] / 100,
            'currency' => $payment['currency'],
            'raw_response' => $payment
        ];

        $adsPromotion = $service->activatePromotion($data);

        return response()->json([
            'success' => true,
            'message' => 'Ad promotion activated successfully',
            'ads_promotion' => $adsPromotion->load('product', 'receipts')
        ]);
    }
}
