<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscriptions;
use App\Models\SubscriptionReceipt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Handle subscription activation and receipt creation.
     *
     * @param int $subscriptionId
     * @param string $paymentMethod
     * @param array $paymentData
     * @return UserSubscriptions
     */
    public function subscribe(int $subscriptionId, string $paymentMethod, array $paymentData = []): UserSubscriptions
    {
        return DB::transaction(function () use ($subscriptionId, $paymentMethod, $paymentData) {

            /** @var \App\Models\User|null $user */
            $user = Auth::user() ?? null;

            if (! $user instanceof User) {
                $user = User::findOrFail($paymentData['user_id'] ?? null);
            }

            $transactionId = $paymentData['transaction_id'] ?? null;
            $reference = $paymentData['reference_number'] ?? null;

            // Idempotency check — prevent duplicate receipts
            $existingReceipt = SubscriptionReceipt::where('transaction_id', $transactionId)
                ->orWhere('reference_number', $reference)
                ->first();

            if ($existingReceipt) {
                Log::info('SubscriptionService: Duplicate payment ignored', [
                    'transaction_id' => $transactionId,
                    'reference' => $reference,
                ]);

                // Return the already associated subscription (for idempotency)
                return $existingReceipt->userSubscription;
            }

            $plan = Subscription::findOrFail($subscriptionId);

            // Compute date range
            $startDate = now();
            $endDate = match ($plan->date_option) {
                'days' => $startDate->copy()->addDays($plan->duration),
                'weeks' => $startDate->copy()->addWeeks($plan->duration),
                'months' => $startDate->copy()->addMonths($plan->duration),
                'years' => $startDate->copy()->addYears($plan->duration),
                default => $startDate->copy()->addDays($plan->duration),
            };

            // Expire existing subscriptions
            UserSubscriptions::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            // Create new active subscription
            $userSub = UserSubscriptions::create([
                'user_id' => $user->id,
                'subscription_id' => $plan->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'active',
            ]);

            // Build snapshot of plan
            $subscriptionSnapshot = [
                'title' => $plan->subscription_title,
                'code' => $plan->subscription_code,
                'price' => $plan->price,
                'vat' => $plan->vat,
                'currency' => $plan->currency,
                'duration' => $plan->duration,
                'date_option' => $plan->date_option,
                'features' => [
                    'case_record' => $plan->case_record,
                    'profile_approval' => $plan->profile_approval,
                    'contact_info' => $plan->contact_info,
                    'direct_message' => $plan->direct_message,
                    'feed_calculator' => $plan->feed_calculator,
                    'disease_predictor' => $plan->disease_predictor,
                    'store' => $plan->store,
                    'no_of_products' => $plan->no_of_products,
                    'customer_support' => $plan->customer_support,
                ],
            ];

            // Create receipt (safe)
            SubscriptionReceipt::create([
                'user_subscription_id' => $userSub->id,
                'user_id' => $user->id,
                'subscription_id' => $plan->id,
                'payment_method' => $paymentMethod,
                'transaction_id' => $transactionId ?? uniqid('txn_'),
                'reference_number' => $reference,
                'amount' => $paymentData['amount'] ?? $plan->price,
                'currency' => $paymentData['currency'] ?? $plan->currency,
                'subscription_snapshot' => $subscriptionSnapshot,
                'metadata' => $paymentData ?? [],
                'paid_at' => now(),
            ]);

            // Activity log + Notification
            $user->logActivity(
                action: 'subscription.created',
                title: 'Subscribed to Plan',
                detail: "User subscribed to {$plan->subscription_title} from {$startDate->toDateString()} to {$endDate->toDateString()}",
                meta: [
                    'subscription_id' => $userSub->id,
                    'plan_id' => $plan->id,
                    'transaction_id' => $transactionId,
                    'reference' => $reference,
                    'status' => 'active',
                ]
            );

            $user->sendNotification(
                type: 'subscription.created',
                title: 'Subscription Activated',
                content: "Your subscription to {$plan->subscription_title} is active until {$endDate->toDateString()}.",
                meta: [
                    'subscription_id' => $userSub->id,
                    'transaction_id' => $transactionId,
                    'reference' => $reference,
                ]
            );

            Log::info('SubscriptionService: Subscription activated successfully', [
                'user_id' => $user->id,
                'subscription_id' => $plan->id,
                'transaction_id' => $transactionId,
            ]);

            return $userSub;
        });
    }
}
