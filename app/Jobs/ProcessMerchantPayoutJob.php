<?php

namespace App\Jobs;

use App\Models\MerchantPayout;
use App\Services\PaystackTransferService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMerchantPayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public MerchantPayout $payout;

    /**
     * Create a new job instance.
     */
    public function __construct(MerchantPayout $payout)
    {
        //
        $this->payout = $payout;
    }

    /**
     * Execute the job.
     */
   public function handle(PaystackTransferService $paystack)
    {
        try {
            if ($this->payout->status !== 'pending') {
                Log::info('Skipping payout already processed', ['payout_id' => $this->payout->id]);
                return;
            }

            $this->payout->update(['status' => 'processing']);

            $transfer = $paystack->transferToMerchant($this->payout);

            $this->payout->update([
                'status' => 'completed',
                'paid_at' => now(),
                'transaction_reference' => $transfer['reference'],
                'metadata' => array_merge($this->payout->metadata ?? []),
                'response_data' => $transfer
            ]);

            Log::info('Merchant payout processed successfully', [
                'payout_id' => $this->payout->id,
                'merchant_id' => $this->payout->merchant_id,
            ]);

        } catch (\Throwable $e) {
            $this->payout->update(['status' => 'failed']);
            Log::error('Merchant payout failed', [
                'payout_id' => $this->payout->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
