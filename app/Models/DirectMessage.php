<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
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
        'type',
        'meta',
        'is_read',
        'is_flagged',
    ];

    protected $casts = [
        'is_read'    => 'boolean',
        'is_flagged' => 'boolean',
        'image'      => 'array',
        'meta' => 'array'
    ];

    protected $appends = ['image_urls', 'product_image_urls'];

    // Accessor: return full URLs for images
    public function getImageUrlsAttribute()
    {
        if (!$this->image) return [];
        return array_map(fn($img) => Storage::url($img), $this->image);
    }

    // get product images urls
    public function getProductImageUrlsAttribute()
    {
        if (!isset($this->meta['product_images']) || !$this->meta['product_images']){
            return [];
        }

        return array_map(fn($img) => Storage::url($img), $this->meta['product_images']);
    }

    protected static function booted()
    {
        static::created(fn($chat) => self::clearMessageCaches($chat));
        static::updated(fn($chat) => self::clearMessageCaches($chat));
        static::deleted(fn($chat) => self::clearMessageCaches($chat));
    }

    public static function clearMessageCaches(?self $message = null): void
    {
        Cache::forget("conversation_{$message->sender_id}_{$message->receiver_id}");
        Cache::forget("conversation_{$message->receiver_id}_{$message->sender_id}");
        Cache::forget("sent_messages_{$message->sender_id}");
        Cache::forget("received_messages_{$message->receiver_id}");
        Cache::forget("chat_list_{$message->sender_id}");
        Cache::forget("chat_list_{$message->receiver_id}");
    }

    // Relationships
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id')->with('profile:id,user_id,profile_image', 'media');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id')->with('profile:id,user_id,profile_image', 'media');
    }
}
