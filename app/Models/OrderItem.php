<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'price',
        'quantity',
        'subtotal',
        'product_snapshot',
    ];

    protected $casts = [
        'product_snapshot' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($item) {
            $item->subtotal = $item->price * $item->quantity;
        });
    }


    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Products::class);
    }
}
