<?php

namespace App\Services;

use App\Models\AdsPromotion;
use App\Models\AdsPromotionReceipt;
use App\Models\Products;
use App\Models\PromotionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdsPromotionService
{
    /**
     * Create a new AdsPromotion and receipt (called by webhook or confirmed payment).
     */
    public function activatePromotion(array $data): AdsPromotion
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user() ?? User::findOrFail($data['user_id'] ?? null);

            if (! $user instanceof User) {
                $user = User::findOrFail($data['user_id'] ?? null);
            }

            // Idempotency check
            $existingReceipt = AdsPromotionReceipt::where('transaction_id', $data['transaction_id'])->first();
            if ($existingReceipt) {
                return $existingReceipt->adsPromotion;
            }

            $plan = PromotionPlan::findOrFail($data['promotion_plan_id']);

            $product = Products::findOrFail($data['product_id']);

            $quantity = isset($data['quantity']) ? max(1, intval($data['quantity'])) : 1;

            // Create new ad promotion
            $startDate = Carbon::now();
            $duration  = intval($plan->duration);

            // multiply duration by quantity
            $endDate = match(strtolower($plan->date_option)){
                'days' => $startDate->copy()->addDays($duration * $quantity),
                'weeks' => $startDate->copy()->addWeeks($duration * $quantity),
                'months' => $startDate->copy()->addMonths($duration * $quantity),
                'years' => $startDate->copy()->addYears($duration * $quantity),
              default => $startDate->copy()->addDays($duration * $quantity),
            };

            $promotion = AdsPromotion::create([
                'user_id' => $user->id,
                'product_id' => $data['product_id'],
                'promotion_plan_id' => $plan->id,
                'active_role' => $user->activeRole->name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'active',
            ]);

            // Take snapshot for record integrity
            $promotionSnapshot = [
                'plan_code' => $plan->promotion_code,
                'plan_title' => $plan->title,
                'plan_price' => $plan->price,
                'currency' => $plan->currency,
                'vat' => $plan->vat,
                'duration' => $plan->duration,
                'date_option' => $plan->date_option,
                'status' => $plan->status,
                'max_products' => $plan->no_of_products,
            ];

            // Record payment receipt
            AdsPromotionReceipt::create([
                'ads_promotion_id' => $promotion->id,
                'user_id' => $user->id,
                'promotion_plan_id' => $plan->id,
                'payment_method' => $data['payment_method'],
                'transaction_id' => $data['transaction_id'],
                'reference_number' => $data['reference_number'],
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'promotion_snapshot' => $promotionSnapshot,
                'metadata' => $data ?? [],
                'paid_at' => Carbon::now(),
            ]);

            // Log + Notify
            $user->logActivity(
                'ads_promotion_created',
                'Ad Promotion Activated',
                "Ad promotion started for product {$product->name} using plan '{$plan->title}'.",
                ['promotion_id' => $promotion->id, 'plan_id' => $plan->id]
            );

            $user->sendNotification(
                'ad_promotion_active',
                'Promotion Activated',
                "Your product has been successfully promoted using '{$plan->title}' plan.",
                ['promotion_id' => $promotion->id, 'plan_id' => $plan->id]
            );

            return $promotion;
        });
    }
}
