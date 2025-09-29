<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class DirectMessage extends Model
{
    use SoftDeletes;

    protected $table = 'direct_messages';

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'content',
        'image',
        'is_read',
        'is_flagged',
    ];

    protected $casts = [
        'is_read'    => 'boolean',
        'is_flagged' => 'boolean',
        'image'      => 'array', // stored as JSON
    ];

    protected $appends = ['image_urls'];

    // Accessor: return full URLs for images
    public function getImageUrlsAttribute()
    {
        if (!$this->image) return [];
        return array_map(fn($img) => Storage::url($img), $this->image);
    }

    // Relationships
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id')->with('profile:id,user_id,profile_picture');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id')->with('profile:id,user_id,profile_picture');
    }
}
