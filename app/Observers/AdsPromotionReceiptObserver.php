<?php

namespace App\Observers;

use App\Models\AdsPromotionReceipt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdsPromotionReceiptObserver
{
    public function created(AdsPromotionReceipt $receipt): void
    {
        Cache::forget("user_{$receipt->user_id}_ads_promotion_receipts");
        Log::info('New ads promotion receipt created', [
            'user_id' => $receipt->user_id,
            'reference_number' => $receipt->reference_number,
        ]);
    }

    public function deleted(AdsPromotionReceipt $receipt): void
    {
        Cache::forget("user_{$receipt->user_id}_ads_promotion_receipts");
    }
}
