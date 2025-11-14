<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Traits\HasAuthorAttribute;
use Illuminate\Support\Facades\Log;
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

    // In ForumChat model, add at the top:
    protected const ALL_ROLES = [
        'veterinary_doctor',
        'veterinary_clinic',
        'veterinary_paraprofessional',
        'pet_owner',
        'livestock_farmer',
        'vendor',
        'others'
    ];

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


        static::created(function ($chat) {
            self::clearCaches($chat);
        });

        static::updated(function ($chat) {
            self::clearCaches($chat);
        });

        static::deleted(function ($chat) {
            self::clearCaches($chat);
        });

        static::restored(function ($chat) {
            self::clearCaches($chat);
        });
    }

    /**
     * Clear forum-related caches
     */
    public static function clearCaches(?self $chat = null): void
    {
        // Existing forgets (keep these)
        Cache::forget("forum_chats_admin");
        if ($chat?->slug) {
            Cache::forget("forum_show_{$chat->slug}");
        }
        Cache::forget('admin_forums');
        if ($chat?->id) {
            Cache::forget("forum_likers_{$chat->id}");
            Cache::forget("forum_chat_comment_{$chat->id}");
        }

        // New: Clear index caches per affected role
        Cache::forget('forum_chats_index_admin');
        Cache::forget("trending_forums_chat_admin");

        if ($chat?->visibility) {
            Cache::forget("forum_visibility_{$chat->visibility}");
            if ($chat->visibility === 'everyone') {
                foreach (self::ALL_ROLES as $role) {
                    Cache::forget("forum_chats_index_{$role}");
                    Cache::forget("trending_forums_chat_{$role}");
                }
            } else {
                Cache::forget("forum_chats_index_{$chat->visibility}");
                Cache::forget("trending_forums_chat_{$chat->visibility}");
            }
        }

        // Existing per-user clears
        if ($chat?->user_id && $chat?->user_type) {
            $classBase = class_basename($chat->user_type);
            Log::info("Clearing forum caches for user type: {$classBase}, user ID: {$chat->user_id}");

            Cache::forget("user_forum_posts_{$classBase}_{$chat->user_id}");
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
