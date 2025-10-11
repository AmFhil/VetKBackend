<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
