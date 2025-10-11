<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_user_id',
        'buyer_user_id',
        'tracking_number',
        'payment_method',
        'payment_status',
        'status',
        'total_amount',
        'price',
        'quantity',
        'timeline'
    ];

    protected $casts = [
        'timeline' => 'array',
    ];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_user_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function confirmation()
    {
        return $this->hasOne(OrderConfirmation::class);
    }

    public function complaints()
    {
        return $this->hasMany(OrderComplaint::class);
    }
}
