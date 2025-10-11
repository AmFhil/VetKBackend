<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderComplaint extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'user_id',
        'reason',
        'status'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
