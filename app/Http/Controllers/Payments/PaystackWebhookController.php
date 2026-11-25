<?php

namespace App\Http\Controllers\Payments;

use App\Events\DirectMessageSent;
use App\Http\Controllers\Controller;
use App\Models\DirectMessage;
use App\Models\MerchantPayout;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\OrderConfirmation;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Products;
use App\Models\User;
use App\Services\AdsPromotionService;
use App\Services\OrderService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaystackWebhookController extends Controller
{
    protected SubscriptionService $subscriptionService;
    protected AdsPromotionService $adsPromotionService;
    protected OrderService $orderService;

    public function __construct(SubscriptionService $subscriptionService, AdsPromotionService $adsPromotionService, OrderService $orderService)
    {
        $this->subscriptionService = $subscriptionService;
        $this->adsPromotionService = $adsPromotionService;
        $this->orderService = $orderService;
    }


    /**
     * Handle incoming Paystack webhooks.
     */
    public function handle(Request $request)
    {
        $secret = config('services.paystack.secret_key');

        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        // Verify request authenticity
        if (!$signature || !hash_equals(hash_hmac('sha512', $payload, $secret), $signature)) {
            Log::warning('Paystack webhook: Invalid signature attempt', [
                'ip' => $request->ip(),
            ]);
            return response('Invalid signature', Response::HTTP_UNAUTHORIZED);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        Log::info("Paystack webhook received: {$event}", [
            'reference' => $data['reference'] ?? null,
            'transaction_id' => $data['id'] ?? null,
        ]);

        try {
            switch ($event) {
                /**
                 * Subscription Payment Success
                 * This triggers after successful payment verification.
                 */
                case 'charge.success':
                    $metadata = $data['metadata'] ?? [];

                    if (($metadata['type'] ?? null) === 'subscription') {
                        $this->handleSubscriptionPayment($data);
                    } elseif (($metadata['type'] ?? null) === 'ads_promotion') {
                        $this->handleAdsPromotionPayment($data);
                    } elseif (($data['metadata']['type'] ?? null) === 'order') {
                        $this->handleProductPaymentSuccess($data);
                    } else {
                        Log::info('Paystack charge.success ignored — no recognized type', ['metadata' => $metadata]);
                    }
                    break;
                case 'transfer.success':
                    $this->handleMerchantTransferSuccess($data);
                    break;

                case 'transfer.failed':
                    $this->handleMerchantTransferFailed($data);
                    break;

                case 'dedicatedaccount.assigned':
                    $this->handleDedicatedAccountAssigned($data);
                    break;

                /**
                 * Handle refund or failed charge events later
                 */
                case 'charge.failed':
                    Log::warning('Paystack charge failed', [
                        'reference' => $data['reference'],
                        'email' => $data['customer']['email'] ?? null,
                    ]);
                    break;

                default:
                    Log::info("Paystack webhook ignored: {$event}");
                    break;
            }

            return response('Webhook processed', Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::error('Paystack webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response('Error processing webhook', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Handle successful Paystack payment for a subscription.
     */
    protected function handleSubscriptionPayment(array $data): void
    {
        // Ensure metadata exists
        $metadata = $data['metadata'] ?? [];
        if (!isset($metadata['subscription_id'], $metadata['user_id'])) {
            Log::warning('Paystack webhook missing metadata', ['data' => $data]);
            return;
        }

        // Normalize amount to USD
        $amount = isset($data['amount']) ? $data['amount'] / 100 : 0;

        // Delegate to SubscriptionService (idempotent)
        $this->subscriptionService->subscribe(
            $metadata['subscription_id'],
            'paystack',
            [
                'transaction_id' => $data['id'],
                'reference_number' => $data['reference'],
                'amount' => $amount,
                'currency' => $data['currency'] ?? 'USD',
                'user_id' => $metadata['user_id'],
                'metadata' => $metadata,
            ]
        );

        Log::info('Subscription payment processed successfully via webhook', [
            'reference' => $data['reference'],
            'user_id' => $metadata['user_id'],
        ]);
    }

    /**
     * Handle successful Paystack payment for a adsPromotion.
     */
    protected function handleAdsPromotionPayment(array $data): void
    {
        // Ensure metadata exists
        $metadata = $data['metadata'] ?? [];
        if (!isset($metadata['ads_promotion_id'], $metadata['user_id'])) {
            Log::warning('Paystack webhook missing metadata', ['data' => $data]);
            return;
        }

        // Normalize amount to USD
        $amount = isset($data['amount']) ? $data['amount'] / 100 : 0;

        $userId = $metadata['user_id'];
        $productId = $metadata['product_id'];
        $promotionPlanId = $metadata['promotion_plan_id'];

        $data = [
            'user_id' => $userId,
            'product_id' => $productId,
            'promotion_plan_id' => $promotionPlanId,
            'payment_method' => 'paystack',
            'transaction_id' => $data['id'], // Paystack’s internal txn ID
            'reference_number' => $data['reference'], // public ref
            'amount' => $amount,
            'currency' => $data['currency'],
            'raw_response' => $data,
            'metadata' => $metadata,
        ];

        $this->adsPromotionService->activatePromotion($data);

        Log::info('Ads-Promotion payment processed successfully via webhook', [
            'reference' => $data['reference'],
            'user_id' => $metadata['user_id'],
        ]);
    }

    /**
     * Handle product payment success
     */
    protected function handleProductPaymentSuccess(array $data): void
    {
        $response = Http::withToken(config('services.paystack.secret_key'))
            ->get("https://api.paystack.co/transaction/verify/{$data['reference']}");

        if (!$response->successful()) {
            Log::error('Paystack product payment verification failed', [
                'reference' => $data['reference'],
                'response' => $response->body()
            ]);
            return;
        }

        DB::transaction(function () use ($data) {
            $metadata = $data['metadata'];
            $product = Products::findOrFail($metadata['product_id']);
            $quantity = $metadata['quantity'];
            $totalAmount = $data['amount'] / 100;
            $buyer_user = $metadata['buyer_user_id'];
            $buyerUser = User::findOrFail($buyer_user);
            $merchant_user = $metadata['merchant_user_id'];
            $transaction_id = $data['transaction_id'] ?? $data['id'];
            $reference = $data['reference'];

            Log::info('OrderService: Creating order from payment', [
                'transaction_id' => $transaction_id,
                'reference' => $reference,
                'buyer_user_id' => $buyer_user,
                'merchant_user_id' => $merchant_user,
                'product_id' => $product->id,
            ]);

            // idempotency check
            $existingPayment = Payment::where('transaction_id', $transaction_id)->first();
            if ($existingPayment) {
                return $existingPayment->order;
            }

            // Create order
            $order = Order::create([
                'merchant_user_id' => $metadata['merchant_user_id'],
                'buyer_user_id' => $metadata['buyer_user_id'],
                'product_id' => $product->id,
                'tracking_number' => strtoupper(uniqid('TRK')),
                'payment_method' => 'paystack',
                'payment_status' => 'paid',
                'status' => 'active',
                'total_amount' => $totalAmount,
                'price' => $metadata['price'],
                'quantity' => $quantity,
                'timeline' => [
                    'created_at' => now()->toDateTimeString(),
                    'events' => ['Order created after payment'],
                ],
            ]);

            // Create order item
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->product_name,
                'price' => $metadata['price'],
                'quantity' => $quantity,
                'subtotal' => $totalAmount,
                'product_snapshot' => $product->toArray(),
            ]);

            // Confirmation record
            OrderConfirmation::create(['order_id' => $order->id]);

            // Reduce stock
            $product->decrement('available_unit', $quantity);

            // payment record
            Payment::create([
                'order_id' => $order->id,
                'method' => 'paystack',
                'amount' => $totalAmount,
                'status' => 'successful',
                'transaction_id' => $transaction_id,
                'reference_number' => $reference,
                'meta' => $metadata,
                'gateway_response' => $data
            ]);

            // Notify merchant
            $message = DirectMessage::create([
                'sender_id' => $metadata['buyer_user_id'],
                'receiver_id' => $metadata['merchant_user_id'],
                'type' => 'order',
                'content' => "{$buyerUser->first_name} {$buyerUser->last_name} just made an order for {$product->product_name}",
                'meta' => [
                    'order_id' => $order->id,
                    'product_images' => $product->images,
                    'view_url'       => route('order.show', $order->id),
                    'cancel_url'     => route('order.cancel', $order->id),
                ]
            ]);

            event(new DirectMessageSent($message));

            $buyerUser->logActivity('order_created', 'You placed an order', "Order #{$order->tracking_number} for {$product->product_name}");
            $order->merchant->sendNotification(
                'order_created',
                'New Order Received',
                "{$buyerUser->first_name} {$buyerUser->last_name} just placed an order for {$product->product_name}.",
                ['order_id' => $order->id]
            );

            Log::info('OrderService: Order created successfully from payment', [
                'order_id' => $order->id,
                'transaction_id' => $transaction_id,
                'reference' => $reference,
            ]);
        });
    }

    /**
     * Handle successful merchant payout confirmation
     */
    protected function handleMerchantTransferSuccess(array $data): void
    {
        $reference = $data['reference'] ?? null;

        $payout = MerchantPayout::where('transaction_reference', $reference)->first();
        if (!$payout) {
            Log::warning('Paystack transfer.success: payout not found', ['reference' => $reference]);
            return;
        }

        $payout->update([
            'status' => 'completed',
            'paid_at' => now(),
            'response_data' => $data,
        ]);

        Log::info('Merchant payout confirmed successful by Paystack', [
            'payout_id' => $payout->id,
            'merchant_id' => $payout->merchant_id,
            'reference' => $reference,
        ]);

        // notify merchant
        $payout->merchant->sendNotification(
            'payout_completed',
            'Withdrawal Successful',
            'Your payout has been successfully processed by Paystack.',
            ['order_id' => $payout->order_id, 'reference' => $reference]
        );
    }

    /**
     * Handle failed merchant payout
     */
    protected function handleMerchantTransferFailed(array $data): void
    {
        $reference = $data['reference'] ?? null;

        $payout = MerchantPayout::where('transaction_reference', $reference)->first();
        if (!$payout) {
            Log::warning('Paystack transfer.failed: payout not found', ['reference' => $reference]);
            return;
        }

        $payout->update([
            'status' => 'failed',
            'response_data' => $data,
        ]);

        Log::error('Merchant payout failed (Paystack)', [
            'payout_id' => $payout->id,
            'merchant_id' => $payout->merchant_id,
            'reference' => $reference,
        ]);

        // notify merchant
        $payout->merchant->sendNotification(
            'payout_failed',
            'Withdrawal Failed',
            'Your payout could not be processed. Please verify your Paystack details.',
            ['order_id' => $payout->order_id, 'reference' => $reference]
        );
    }

    protected function handleDedicatedAccountAssigned(array $data): void
    {
        $metadata = $data['metadata'] ?? [];
        if (!isset($metadata['merchant_id'])) {
            Log::warning('Paystack webhook missing metadata for dedicated account assignment', ['data' => $data]);
            return;
        }

        $merchantId = $metadata['merchant_id'];
        $merchant = MerchantProfile::where('user_id', $merchantId)->first();
        if (!$merchant) {
            Log::warning('MerchantProfile not found for dedicated account assignment', ['merchant_id' => $merchantId]);
            return;
        }

        $accountId = $data['dedicated_account_id'] ?? null;
        if (!$accountId) {
            Log::warning('Paystack webhook missing dedicated_account_id', ['data' => $data]);
            return;
        }

        $merchant = MerchantProfile::where('paystack_dedicated_account_id', $accountId)->first();
        if (!$merchant) {
            Log::warning('MerchantProfile not found for dedicated account assignment by account ID', ['account_id' => $accountId]);
            return;
        }

        $merchant->update([
            'paystack_recipient_code' => $data['recipient_code'],
            'paystack_connected' => true,
        ]);

        // send notification to merchant
        $merchant->user->sendNotification(
            'dedicated_account_connected',
            'Paystack Dedicated Account Connected',
            'Your Paystack dedicated account has been successfully connected.',
            []
        );
    }
}
