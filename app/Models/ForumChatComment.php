<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Traits\HasAuthorAttribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class ForumChatComment extends Model
{
    use SoftDeletes, HasAuthorAttribute;

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
            if ($comment->forum) {
                ForumChat::clearCaches($comment->forum);
            }
        });

        // After delete (soft or force) adjust counts carefully
        static::deleted(function ($comment) {
            $forum = $comment->forum;
            if (!$forum) {
                return;
            }

            // If it was a soft delete, simply decrement by 1
            if ($comment->trashed()) {
                $forum->decrement('comments_count');
            } else {
                // For force delete recompute comments_count from DB to avoid double-decrements
                if (method_exists($forum, 'comments')) {
                    $forum->comments_count = $forum->comments()->count();
                    $forum->save();
                } else {
                    // fallback: decrement once if comments relation not available
                    $forum->decrement('comments_count');
                }
            }

            self::clearCaches($comment);
            if ($forum) {
                ForumChat::clearCaches($forum);
            }
        });

        static::updated(function ($comment) {
            self::clearCaches($comment);
            if ($comment->forum) {
                ForumChat::clearCaches($comment->forum);
            }
        });

        // handle related cleanup on deleting
        static::deleting(function ($comment) {
            // if force-deleting, remove related resources permanently
            if ($comment->isForceDeleting()) {
                if ($comment->forum && method_exists($comment->forum, 'members')) {
                    $comment->forum->members()->delete();
                }
                // force delete replies
                if ($comment->replies()->exists()) {
                    $comment->replies()->forceDelete();
                }
            }
        });

        // when restoring a soft-deleted comment increment forum counter
        static::restored(function ($comment) {
            if ($comment->forum) {
                $comment->forum->increment('comments_count');
                self::clearCaches($comment);
                ForumChat::clearCaches($comment->forum);
            }
        });
    }

    /**
     * Clear forum-related caches
     */
    private static function clearCaches(?self $comment = null): void
    {

        // Forget index and admin listing
        Cache::forget("forum_chats_admin");


        // specific forum show cache
        if ($comment?->forum?->slug) {
            Cache::forget("forum_show_{$comment->forum->slug}");
        }

        Cache::forget('admin_forums');
        Cache::forget("forum_chat_comment_{$comment->forum->id}");
        Log::info("cleared cache comment for forum id:{$comment->forum->id}");

        if ($comment->forum->user_id && $comment->forum->user_type) {
            $classBase = class_basename($comment->forum->user_type);

            // Forget per-user cache variants
            Cache::forget("forum_chats_index_{$classBase}_{$comment->forum->user_id}");
            Cache::forget("trending_forums_chat_{$classBase}_{$comment->forum->user_id}");
            Cache::forget("forum_visibility_{$classBase}_{$comment->forum->user_id}_{$comment->forum->visibility}");
        }
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
