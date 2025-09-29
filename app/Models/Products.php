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
        'product_id',
        'product_name',
        'category',
        'description',
        'location',
        'price',
        'images',
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
        'images' => 'array'
    ];

    protected $appends = ['images_url'];

    public function getImagesUrlAttribute()
    {
        return collect($this->images ?? [])
            ->map(fn($image) => Storage::url($image))
            ->toArray();
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
        return $this->belongsToMany(Tag::class, 'product_tag', 'product_id', 'tag_id')
            ->withTimestamps();
    }
}
