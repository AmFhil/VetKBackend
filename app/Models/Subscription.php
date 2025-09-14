<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'subscription_title',
        'subscription_code',
        'currency',
        'price',
        'vat',
        'date_option',
        'duration',
        'case_record',
        'profile_approval',
        'contact_info',
        'direct_message',
        'feed_calculator',
        'disease_predictor',
        'store',
        'no_of_products',
        'customer_support',
    ];

    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'subscription_id');
    }
}

