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
        'image',
        'status',
        'visibility',
        'views_count',
        'shares_count',
        'comments_count',
        'likes_count',
    ];

    protected $appends = ['author', 'image_url'];

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
    public function getImageUrlAttribute()
    {
        return $this->image ? Storage::url($this->image) : null;
    }

    protected static function booted()
    {
        static::deleting(function ($chat) {
            if ($chat->isForceDeleting()) {
                $chat->comments()->forceDelete();
                $chat->likes()->delete();
                $chat->members()->delete();
            } else {
                $chat->comments()->delete();
            }
        });

        static::restoring(function ($chat) {
            $chat->comments()->withTrashed()->restore();
            // $chat->likes()->withTrashed()->restore();
        });

        foreach (['created', 'updated', 'deleted', 'restored'] as $event) {
            static::$event(fn($chat) => self::clearCaches($chat));
        }
    }

    /**
     * Clear forum-related caches
     */
    public static function clearCaches(?self $chat = null): void
    {

        // Forget index and admin listing
        Cache::forget("forum_chats_admin");

        // Forget single forum show cache (if slug available)
        if ($chat?->slug) {
            Cache::forget("forum_show_{$chat->slug}");
        }

        Cache::forget('admin_forums');

        if ($chat?->id) {
            Cache::forget("forum_likers_{$chat->id}");
            Cache::forget("forum_chat_comment_{$chat->id}");
        }

        if ($chat?->user_id && $chat?->user_type) {
            $classBase = class_basename($chat->user_type);

            // Forget per-user cache variants
            Cache::forget("forum_chats_index_{$classBase}_{$chat->user_id}");
            Cache::forget("trending_forums_chat_{$classBase}_{$chat->user_id}");
            Cache::forget("forum_visibility_{$classBase}_{$chat->user_id}_{$chat->visibility}");
        }
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

    public function likers()
    {
        return $this->hasMany(ForumChatLike::class, 'forum_chat_id')
            ->with('user', 'user.profile', 'user.activeRole');
    }

    public function comments()
    {
        return $this->hasMany(ForumChatComment::class, 'forum_chat_id');
    }

    public function hasLiked($user)
    {
        if (!$user) return false;

        return $this->likes()
            ->where('user_id', $user->id)
            ->where('user_type', get_class($user))
            ->exists();
    }

    public function members()
    {
        return $this->morphMany(Membership::class, 'joinable');
    }
}
