<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionPlan extends Model
{
    use HasFactory;
    protected $table= 'promotion_plan';

    protected $fillable = [
        'promotion_code',
        'title',
        'currency',
        'price',
        'vat',
        'date_option',
        'duration',
        'no_of_products',
        'status',
    ];

    public function adsPromotion()
    {
        return $this->hasMany(AdsPromotion::class, 'promotion_plan_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInActive($query)
    {
        return $query->where('status', 'inactive');
    }

    
}
