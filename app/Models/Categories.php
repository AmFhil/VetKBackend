<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Categories extends Model
{
    //
    protected $fillable = [
        'name',
        'image'
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(){
        return $this->image ? Storage::url($this->image) : null;
    }

    protected static function booted()
    {
        static::created(fn($category) => self::clearCaches($category));
        static::updated(fn($category) => self::clearCaches($category));
        static::deleted(fn($category) => self::clearCaches($category));
    }

    protected static function clearCaches(?self $categories = null ){
        Cache::forget('Categories');
        Cache::forget('admin_categories');
    }
}
