<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSupport extends Model
{
    //
    protected $table = 'customer_supports';

    protected $fillable = [
        'user_id',
        'category',
        'complain'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
