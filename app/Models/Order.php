<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

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

    protected static function booted()
    {
        static::creating(function ($order) {
            if (empty($order->tracking_number)) {
                $order->tracking_number = strtoupper(uniqid('TRK'));
            }
        });

        static::saved(function ($order) {
            self::clearCacheFor($order);
        });

        static::deleted(function ($order) {
            self::clearCacheFor($order);
        });

        static::updated(function ($order) {
            self::clearCacheFor($order);
        });

        static::created(function ($order) {
            self::clearCacheFor($order);
        });

        static::restored(function ($order) {
            self::clearCacheFor($order);
        });

        static::deleting(function ($order) {
            if ($order->forceDeleting()) {
                $order->items()->forceDelete();
                $order->confirmation()->forceDelete();
                $order->complaints()->forceDelete();
                $order->payment()->forceDelete();
            } else {
                $order->items()->delete();
                $order->confirmation()->delete();
                $order->complaints()->delete();
                $order->payment()->delete();
            }
        });
    }

    protected static function clearCacheFor(?self $order = null): void
    {
        Cache::forget("buyer_orders_{$order->buyer_user_id}");
        Cache::forget("merchant_orders_{$order->merchant_user_id}");
        Cache::forget("order_details_{$order->id}");
    }

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
        return $this->hasOne(OrderItem::class);
    }

    public function confirmation()
    {
        return $this->hasOne(OrderConfirmation::class);
    }

    public function complaints()
    {
        return $this->hasMany(OrderComplaint::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function merchantPayouts()
    {
        return $this->hasOne(MerchantPayout::class, 'order_id');
    }
}
