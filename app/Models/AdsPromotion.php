<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AdsPromotion extends Model
{
    use HasFactory;

    protected $table = 'ads_promotions';

    protected $fillable = [
        'user_id',
        'product_id',
        'promotion_plan_id',
        'active_role',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date'   => 'datetime',
    ];

    protected static function booted()
    {
        static::created(function ($adsPromotion) {
            self::clearCaches($adsPromotion);
        });

        static::updated(function ($adsPromotion) {
            self::clearCaches($adsPromotion);
        });

        static::deleted(function ($adsPromotion) {
            self::clearCaches($adsPromotion);
        });
    }

    /** Relationships */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Products::class, 'product_id');
    }

    public function promotionPlan()
    {
        return $this->belongsTo(PromotionPlan::class, 'promotion_plan_id');
    }

    public function receipts()
    {
        return $this->hasMany(AdsPromotionReceipt::class, 'ads_promotion_id');
    }

    public static function clearCaches(?self $adsPromotion = null): void
    {
        Cache::forget("user_{$adsPromotion->user_id}_adsPromotions");
        Cache::forget("admin_users_by_promotion_" . md5($adsPromotion->promotionPlan->title));

        for ($i = 1; $i <= 10; $i++) {
            Cache::forget("admin_user_promotions_page_{$i}");
            Cache::forget("admin_promotion_approvals_page_{$i}");
        }
    }

    /** Scope for active promotions */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('end_date', '>=', now());
    }

    /** Auto-expire checker */
    public function isExpired(): bool
    {
        return $this->end_date < Carbon::now() || $this->status === 'expired';
    }
}
