<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Traits\HasAuthorAttribute;
use Illuminate\Support\Facades\Storage;

class ForumChat extends Model
{
    use SoftDeletes, HasAuthorAttribute;

    protected $table = 'forum_chats';

    protected $fillable = [
        'user_id',
        'user_type',
        'slug',
        'title',
        'content',
        'images',
        'status',
        'visibility',
        'views_count',
        'shares_count',
        'comments_count',
        'likes_count',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    protected $appends = ['author', 'images'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($forumChat) {
            if (empty($forumChat->slug)) {
                $forumChat->slug = Str::slug($forumChat->title) . '-' . uniqid();
            }
        });
    }

    // get image url
    public function getImagesAttribute()
    {
       return collect($this->images ?? [])
            ->map(fn($image) => Storage::url($image))
            ->toArray();
    }

    protected static function booted()
    {
        static::deleting(function ($chat) {
            if ($chat->isForceDeleting()) {
                $chat->comments()->forceDelete();
                $chat->likes()->forceDelete();
            } else {
                $chat->comments()->delete();
                $chat->likes()->delete();
            }
        });

        static::restoring(function ($chat) {
            $chat->comments()->withTrashed()->restore();
            $chat->likes()->withTrashed()->restore();
        });

        foreach (['created', 'updated', 'deleted', 'restored'] as $event) {
            static::$event(fn($chat) => self::clearCaches($chat));
        }
    }

    /**
     * Clear forum-related caches
     */
    private static function clearCaches(?self $chat = null): void
    {

        // Forget index and admin listing
        Cache::forget("forum_chats_index");
        Cache::forget("forum_chats_admin");

        // Forget trending caches
        Cache::forget("trending_forums_chat");

        // Forget single forum show cache (if slug available)
        if ($chat?->slug) {
            Cache::forget("forum_show_{$chat->slug}");
        }

        Cache::forget('admin_forums');
    }

    // Relationships
    public function user()
    {
        return $this->morphTo();
    }

    public function likes()
    {
        return $this->hasMany(ForumChatLike::class, 'forum_chat_id');
    }

    public function comments()
    {
        return $this->hasMany(ForumChatComment::class, 'forum_chat_id');
    }
}
