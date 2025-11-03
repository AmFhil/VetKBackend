<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'method',
        'transaction_id',
        'reference_number',
        'amount',
        'currency',
        'status',
        'meta',
        'gateway_response'            
    ];

    protected $casts = [
        'meta' => 'array',
        'gateway_response' =
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
