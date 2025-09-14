<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    protected $table = 'user_subscriptions';

    protected $fillable = [
        'user_id',
        'subscription_id',
        'start_date',
        'end_date',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    // Helper to check if subscription is active
    public function isActive(): bool
    {
        return $this->status === 'active' && now()->between($this->start_date, $this->end_date);
    }
}
