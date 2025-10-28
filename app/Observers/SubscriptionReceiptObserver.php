<?php

namespace App\Observers;

use App\Models\SubscriptionReceipt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SubscriptionReceiptObserver
{
    public function created(SubscriptionReceipt $receipt): void
    {
        Cache::forget("user_{$receipt->user_id}_receipts");
        Log::info('New subscription receipt created', [
            'user_id' => $receipt->user_id,
            'reference_number' => $receipt->reference_number,
        ]);
    }

    public function deleted(SubscriptionReceipt $receipt): void
    {
        Cache::forget("user_{$receipt->user_id}_receipts");
    }
}
