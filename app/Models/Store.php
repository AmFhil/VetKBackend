<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Store extends Model
{
    use SoftDeletes;
    //
    protected $table = 'store';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'role',
        'store_name',
        'email',
        'phone_number',
        'location',
        'picture',
        'availability',
        'longitude',
        'latitude',
    ];

    protected $casts = [
        'availability' => 'boolean',
    ];

    protected $appends = ['picture_url'];

    public function getPictureUrlAttribute()
    {
        return $this->picture ? Storage::url($this->picture) : null;
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($store) {
            if ($store->isForceDeleting()) {
                $store->products()->forceDelete();
                $store->ratings()->delete();
            } else {
                $store->products()->delete();
            }
        });
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


    public function products()
    {
        return $this->hasMany(Products::class, 'store_id');
    }

    public function scopeNearest($query, float $latitude, float $longitude)
    {
        $haversine = "(6371 * acos(cos(radians(?))
                    * cos(radians(latitude))
                    * cos(radians(longitude) - radians(?))
                    + sin(radians(?))
                    * sin(radians(latitude))))";

        return $query->select('store.*')
            ->selectRaw("$haversine as distance", [$latitude, $longitude, $latitude])
            ->orderBy('distance', 'asc');
    }
}
