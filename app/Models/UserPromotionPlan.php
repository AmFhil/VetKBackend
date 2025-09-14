<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPromotionPlan extends Model
{
    use HasFactory;

    protected $table = 'user_promotion_plan'; // keep singular if that’s your DB
    protected $fillable = [
        'user_id',
        'role',
        'promotion_title',
        'promotion_id',
        'price',
        'no_of_products',
        'start_date',
        'end_date',
        'status'
    ];

    // A user promotion plan belongs to one user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // A user promotion plan belongs to one promotion plan
    public function promotionPlan()
    {
        return $this->belongsTo(PromotionPlan::class, 'promotion_id');
    }

    // A user promotion plan can have many ads promotions
    public function adsPromotions()
    {
        return $this->hasMany(AdsPromotion::class, 'user_promotion_plan_id');
    }
}
