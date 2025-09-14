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

    // A promotion plan can belong to many users (through user_promotion_plan)
    public function userPromotionPlans()
    {
        return $this->hasMany(UserPromotionPlan::class, 'promotion_id');
    }
}
