<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantPayout extends Model
{
    //
    protected $table = 'merchant_payouts';

    protected $fillable = [
        'merchant_id',
        'order_id',
        'paystack_transfer_code',
        'amount',
        'currency',
        'status',
        'metadata',
        'response_data',
        'scheduled_for',
        'paid_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'response_data' => 'array',
        'scheduled_for' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected $hidden = [
        'response_data'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
