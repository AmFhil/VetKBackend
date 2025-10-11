<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Traits\HasAuthorAttribute;

class ForumChatLike extends Model
{
    use HasAuthorAttribute;

    protected $table = 'forum_chat_likes';

    protected $fillable = [
        'forum_chat_id',
        'user_id',
        'user_type',
    ];

    protected $appends = ['author'];

    protected static function booted()
    {
        static::created(function ($like) {
            $like->forum->increment('likes_count');
            self::clearCaches($like);
            if ($like->forum) {
                ForumChat::clearCaches($like->forum);
            }
        });

        static::deleted(function ($like) {
            $like->forum->decrement('likes_count');
            self::clearCaches($like);
            if ($like->forum) {
                ForumChat::clearCaches($like->forum);
            }
        });
    }

    /**
     * Clear forum caches (index, trending, show)
     */
    private static function clearCaches(?self $like = null): void
    {
        // Forget index and admin listing
        Cache::forget("forum_chats_index");
        Cache::forget("forum_chats_admin");

        // trending
        Cache::forget("trending_forums_chat");

        // single forum
        if ($like?->forum?->slug) {
            Cache::forget("forum_show_{$like->forum->slug}");
        }

        Cache::forget('admin_forums');
    }

    // Relationships
    public function forum()
    {
        return $this->belongsTo(ForumChat::class, 'forum_chat_id');
    }

    public function user()
    {
        return $this->morphTo();
    }
}
