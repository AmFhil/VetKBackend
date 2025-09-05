<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    //
    protected $table = 'products';
    protected $fillable = [
        'user_id',
        'store_id',
        'product_name',
        'category',
        'description',
        'tags',
        'location',
        'price',
        'image1',
        'image2',
        'image3',
        'image4',
        'availability',
        'available_unit',
        'is_featured',
        'is_verified'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ratings()
    {
        return $this->morphMany(Rate::class, 'rateable');
    }

    public function averageRating()
    {
        return $this->ratings()->avg('rating');
    }
}
