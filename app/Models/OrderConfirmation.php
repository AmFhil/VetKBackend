<?php

namespace App\Models;

use App\Services\MerchantPayoutScheduler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class OrderConfirmation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'merchant_confirmed',
        'buyer_confirmed',
        'merchant_confirmed_at',
        'buyer_confirmed_at',
    ];

    protected static function booted()
    {
        static::saved(function ($confirmation) {
            if ($confirmation->merchant_confirmed && $confirmation->buyer_confirmed) {
                $confirmation->order->update(['status' => 'completed']);

                try {
                    app(MerchantPayoutScheduler::class)->schedule($confirmation->order);
                } catch (\Throwable $e) {
                    Log::error('Failed to schedule payout after order confirmation', [
                        'order_id' => $confirmation->order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
