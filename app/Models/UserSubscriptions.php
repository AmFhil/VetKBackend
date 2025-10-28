<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class UserSubscriptions extends Model
{
    protected $table = 'user_subscriptions';

    protected $fillable = [
        'user_id',
        'subscription_id',
        'start_date',
        'end_date',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(fn() => self::clearCaches());
        static::updated(fn() => self::clearCaches());
        static::deleted(fn() => self::clearCaches());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(SubscriptionReceipt::class, 'user_subscription_id');
    }

    // Helper to check if subscription is active
    public function isActive(): bool
    {
        return $this->status === 'active' && now()->between($this->start_date, $this->end_date);
    }

    /**
     * 🔧 Clears subscription-related caches
     */
    private static function clearCaches(): void
    {
        // clear first few pages of cached lists
        for ($i = 1; $i <= 5; $i++) {
            Cache::forget("user_subscriptions_page_{$i}");
            Cache::forget("subscription_approvals_page_{$i}");
        }

        // also clear user-specific subscription histories
        foreach (self::pluck('user_id')->unique() as $userId) {
            for ($i = 1; $i <= 5; $i++) {
                foreach ([10, 20] as $perPage) {
                    Cache::forget("user_{$userId}_subscriptions_page_{$i}_per_{$perPage}");
                }
            }
        }

        // ⚠️ optional: if that’s too heavy, you can drop Cache::flush() here
        // Cache::flush();
    }


    public static function getUserSubscriptionsCached($page = 1, $perPage = 20, $ttl = null)
    {
        $ttl = $ttl ?? now()->addMinutes(30);
        $cacheKey = "user_subscriptions_page_{$page}_per_{$perPage}";
        return Cache::remember($cacheKey, $ttl, function () use ($perPage) {
            return self::with(['user', 'subscription', 'user.profile', 'user.activeRole'])
                ->orderByDesc('created_at')
                ->paginate($perPage);
        });
    }

    public static function getApprovalsCached($page = 1, $perPage = 10, $ttl = null)
    {
        $ttl = $ttl ?? now()->addMinutes(30);
        $cacheKey = "subscription_approvals_page_{$page}_per_{$perPage}";
        return Cache::remember($cacheKey, $ttl, function () use ($perPage) {
            return self::with(['user', 'subscription', 'user.profile', 'user.activeRole'])
                ->latest()
                ->paginate($perPage);
        });
    }
}
