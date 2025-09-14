<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AdsPromotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'role',
        'product_title',
        'product_category',
        'location',
        'product_description',
        'price',
        'availability',
        'suspended',
        'available_units',
        'tags',
        'images',
        'date',
        'user_promotion_plan_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'images' => 'array',
    ];


    // Belongs to user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Belongs to store
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    // Belongs to user promotion plan
    public function userPromotionPlan()
    {
        return $this->belongsTo(UserPromotionPlan::class, 'user_promotion_plan_id');
    }

    protected $appends = ['images_url'];

    public function getImagesUrlAttribute()
    {
        if (!$this->images) return [];

        return collect($this->images)->map(function ($img) {
            return Storage::url($img);
        })->toArray();
    }
}
