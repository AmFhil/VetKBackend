<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdsPromotionReceipt extends Model
{
    use SoftDeletes;

    protected $table = 'ads_promotion_receipts';

    protected $fillable = [
        'ads_promotion_id',
        'user_id',
        'promotion_plan_id',
        'payment_method',
        'transaction_id',
        'reference_number',
        'amount',
        'currency',
        'promotion_snapshot',
        'metadata',
        'paid_at',
    ];

    protected $casts = [
        'promotion_snapshot' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    protected $hidden = [
        'metadata',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promotionPlan(): BelongsTo
    {
        return $this->belongsTo(PromotionPlan::class);
    }

    public function adsPromotion(): BelongsTo
    {
        return $this->belongsTo(AdsPromotion::class, 'ads_promotion_id');
    }
}
