<?php

namespace App\Services;

use App\Jobs\ProcessMerchantPayoutJob;
use App\Models\MerchantPayout;
use App\Models\MerchantProfile;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MerchantPayoutScheduler
{
    protected PaystackSubaccountService $PaystackSubaccountService;
    /**
     * Create a new class instance.
     */
    public function __construct(PaystackSubaccountService $PaystackSubaccountService)
    {
        //
        $this->PaystackSubaccountService = $PaystackSubaccountService;
    }

    /**
     * Schedule payout after both sides confirm
     */
    public function schedule(Order $order): void
    {
        if ($order->payment_status !== 'paid') {
            Log::info("Payout skipped — order not yet paid", ['order_id' => $order->id]);
            return;
        }

        $merchant = $order->merchant;
        if (! $merchant) {
            Log::warning("Payout skipped — merchant not found", ['order_id' => $order->id]);
            return;
        }

        //  merchant subaccount exists on Paystack
        // $merchantProfile = $this->PaystackSubaccountService->ensureMerchantSubaccount($merchant);
        $merchantProfile = MerchantProfile::where('user_id', $merchant->id)->first();

        if (! $merchantProfile || ! $merchantProfile->paystack_recipient_code) {
            Log::warning("Payout skipped — merchant has no Paystack code", [
                'merchant_id' => $merchant->id,
                'order_id' => $order->id,
            ]);

            // (optional) notify merchant to connect Paystack
            $merchant->sendNotification(
                'payout_pending_setup',
                'Payout Setup Required',
                'You need to connect your Paystack account to receive payouts.',
                ['order_id' => $order->id]
            );

            return;
        }

        // Idempotency: no duplicate payouts
        if (MerchantPayout::where('order_id', $order->id)->exists()) {
            Log::info("Merchant payout already exists for order", ['order_id' => $order->id]);
            return;
        }

        DB::transaction(function () use ($order, $merchantProfile) {
            $payout = MerchantPayout::create([
                'merchant_id' => $order->merchant_user_id,
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'currency' => $order->currency ?? 'USD',
                'status' => 'pending',
                'scheduled_for' => Carbon::now()->addWeekdays(3), // after 3 working days
                'metadata' => [
                    'paystack_recipient_code' => $merchantProfile->paystack_recipient_code,
                    'created_by' => 'auto_confirmation',
                ],
            ]);

            Log::info("Merchant payout scheduled", [
                'order_id' => $order->id,
                'merchant_id' => $order->merchant_user_id,
                'payout_id' => $payout->id,
            ]);

            ProcessMerchantPayoutJob::dispatch($payout)
                ->delay($payout->scheduled_for);
        });
    }
}
