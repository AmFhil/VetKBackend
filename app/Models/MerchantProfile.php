<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantProfile extends Model
{
    //
    protected $table = 'merchant_profiles';

    protected $fillable = [
        'user_id',
        'paystack_subaccount_code',
        'integration',
        'verified',
        'response_data'
    ];

    protected $casts = [
        'verified' => 'boolean',
        'response_data' => 'array'
    ];

    protected $hidden = [
        'response_data'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
