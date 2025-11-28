<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

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

    protected $casts = [
        'duration' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(fn() => self::clearCaches());
        static::updated(fn() => self::clearCaches());
        static::deleted(fn() => self::clearCaches());
    }

    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscriptions::class, 'subscription_id');
    }

    /**
     * Clears subscription plan related caches
     */
    private static function clearCaches(): void
    {
        Cache::forget('subscriptions_all');

        for ($i = 1; $i <= 5; $i++) {
            Cache::forget("subscriptions_page_{$i}");
        }

        // If you also cache active-only subscriptions
        Cache::forget('subscriptions_active');
    }

    // returns active subscriptions
    public static function getActiveSubscriptions($ttl = null)
    {
        $ttl = $ttl ?? now()->addMinutes(30);
        return Cache::remember('subscriptions_active', $ttl, function (){
            return self::where('status', 'active')
                ->orderBy('price', 'asc')
                ->get();
        });
    }

    // return all subscription
    public static function getAllCached($page = 1, $perPage = 20, $ttl = null){
        $ttl = $ttl ?? now()->addMinutes(10);
        $cacheKey = "subscription_page_{$page}_per_{$perPage}";
        return Cache::remember($cacheKey, $ttl, function () use ($perPage) {
            return self::latest()->paginate($perPage);
        });
    }
}
