<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionReceipt extends Model
{
    use SoftDeletes;
    //
    protected $table = 'subscription_receipts';

     protected $fillable = [
        'user_subscription_id',
        'user_id',
        'subscription_id',
        'payment_method',
        'transaction_id',
        'reference_number',
        'amount',
        'currency',
        'subscription_snapshot',
        'metadata',
        'paid_at',
    ];

    protected $casts = [
        'subscription_snapshot' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    protected $hidden = [
        'metadata'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function userSubscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscriptions::class, 'user_subscription_id');
    }
}
