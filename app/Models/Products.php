<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Products extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'user_id',
        'store_id',
        'product_name',
        'category',
        'description',
        'location',
        'price',
        'image1',
        'image2',
        'image3',
        'image4',
        'availability',
        'available_unit',
        'is_featured',
        'is_verified',
        'product_type'
    ];

    protected $casts = [
        'availability' => 'boolean',
        'is_featured' => 'boolean',
        'is_verified' => 'boolean',
        'price' => 'decimal:2',
    ];

    protected $appends = ['image1_url', 'image2_url', 'image3_url', 'image4_url'];

    public function getImage1UrlAttribute()
    {
        return $this->image1 ? Storage::url($this->image1_url) : null;
    }

    public function getImage2UrlAttribute()
    {
        return $this->image2 ? Storage::url($this->image2_url) : null;
    }

    public function getImage3UrlAttribute()
    {
        return $this->image3 ? Storage::url($this->image3_url) : null;
    }

    public function getImage4UrlAttribute()
    {
        return $this->image4 ? Storage::url($this->image4_url) : null;
    }

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
        return round($this->ratings()->avg('rating') ?? 0, 1);
    }


    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'product_tag')
            ->withTimestamps();
    }
}
