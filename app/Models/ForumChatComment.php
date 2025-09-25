<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Traits\HasAuthorAttribute;

class ForumChatComment extends Model
{
    use HasAuthorAttribute;

    protected $table = 'forum_chat_comments';

    protected $fillable = [
        'forum_chat_id',
        'user_id',
        'user_type',
        'parent_id',
        'comment',
        'flag',
    ];

    protected $appends = ['author'];

    protected static function booted()
    {
        static::created(function ($comment) {
            $comment->forum->increment('comments_count');
            self::clearCaches($comment);
        });

        static::deleted(function ($comment) {
            $comment->forum->decrement('comments_count');
            self::clearCaches($comment);
        });

        static::updated(function ($comment) {
            self::clearCaches($comment);
        });
    }

    /**
     * Clear forum-related caches
     */
    private static function clearCaches(?self $comment = null): void
    {

        // Forget index and admin listing
        Cache::forget("forum_chats_index");
        Cache::forget("forum_chats_admin");

        Cache::forget("forum_trending_page");
        Cache::forget("trending_forums_chat");

        // specific forum show cache
        if ($comment?->forum?->slug) {
            Cache::forget("forum_show_{$comment->forum->slug}");
        }

        Cache::forget('admin_forums');
    }

    // Relationships
    public function forum()
    {
        return $this->belongsTo(ForumChat::class, 'forum_chat_id');
    }

    public function parent()
    {
        return $this->belongsTo(ForumChatComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(ForumChatComment::class, 'parent_id')->with('replies');
    }

    public function user()
    {
        return $this->morphTo();
    }
}
