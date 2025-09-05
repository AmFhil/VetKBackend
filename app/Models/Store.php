<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    //
    protected $table='store';
    protected $primaryKey='id';
    public $timestamps=true;
    protected $keyType='int';

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
        return $this->ratings()->avg('rating') ?: 0;
    }

    public function products()
    {
        return $this->hasMany(Products::class, 'store_id');
    }
}
