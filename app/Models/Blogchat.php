<?php

namespace App\Models;

use App\Traits\HasAuthorAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BlogChat extends Model
{
    use SoftDeletes, HasAuthorAttribute;

    protected $table = 'blog_chats';

    protected $fillable = [
        'title',
        'content',
        'user_id',
        'user_type',
        'slug',
        'category',
        'section',
        'status',
        'picture',
        'likes_count',
        'views_count',
        'shares_count',
        'comments_count',
    ];

    protected $appends = ['author', 'picture_url'];

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

        static::created(fn($chat) => self::clearCaches($chat));
        static::updated(fn($chat) => self::clearCaches($chat));
        static::deleted(fn($chat) => self::clearCaches($chat));
        static::restored(fn($chat) => self::clearCaches($chat));
    }

    public static function clearCaches(?self $chat = null): void
    {
        Cache::forget("blog_show_{$chat->id}");
        Cache::forget("trending_blogs");

        $registry = Cache::get('blog_cache_keys', []);
        Log::info('Clearing cache keys:', $registry);

        foreach ($registry as $key) {
            if (str_starts_with($key, 'blogs_index_') || str_starts_with($key, 'blog_show_') || str_starts_with($key, 'trending_blogs')) {
                Cache::forget($key);
            }
        }

        // Reset the registry
        Cache::forget('blog_cache_keys');
    }

    // get picture url
    public function getPictureUrlAttribute()
    {
        return $this->picture ? Storage::url($this->picture) : null;
    }

    public function user()
    {
        return $this->morphTo();
    }

    public function comments()
    {
        return $this->hasMany(BlogChatComment::class, 'blog_chat_id');
    }

    public function likes()
    {
        return $this->hasMany(BlogChatLike::class, 'blog_chat_id');
    }

    public function likers()
    {
        return $this->hasMany(BlogChatLike::class, 'blog_chat_id')->with('user', 'user.profile', 'user.activeRole');
    }
}
