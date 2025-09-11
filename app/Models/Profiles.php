<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Profiles extends Model
{
    protected $table = 'profile';

    protected $fillable = [
        'bio',
        'profile_image',
        'cover_page_image'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Auto-append extra attributes
    protected $appends = ['profile_image_url', 'cover_page_image_url'];

    // Accessor for profile_image
    public function getProfileImageUrlAttribute()
    {
        return $this->profile_image
            ? Storage::url($this->profile_image)
            : null;
    }

    // Accessor for cover_page_image
    public function getCoverPageImageUrlAttribute()
    {
        return $this->cover_page_image
            ? Storage::url($this->cover_page_image)
            : null;
    }
}

