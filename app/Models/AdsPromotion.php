<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
