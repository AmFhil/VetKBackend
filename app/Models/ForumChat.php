<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ForumChat extends Model
{
    use SoftDeletes;

    protected $table = 'forum_chats';

    protected $fillable = [
        'user_id',
        'user_type',
        'slug',
        'title',
        'content',
        'images',
        'status',
        'views_count',
        'shares_count',
        'comments_count',
        'likes_count',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    // Auto-generate slug on create
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($forumChat) {
            if (empty($forumChat->slug)) {
                $forumChat->slug = Str::slug($forumChat->title) . '-' . uniqid();
            }
        });
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
    }


    protected $appends = ['author'];

    public function getAuthorAttribute()
    {
        if ($this->user instanceof \App\Models\Admin) {
            return [
                'id'    => $this->user->id,
                'name'  => $this->user->first_name . ' ' . $this->user->last_name,
                'image' => $this->user->profile_picture_url,
                'type'  => 'admin',
            ];
        }

        if ($this->user instanceof \App\Models\User) {
            return [
                'id'          => $this->user->id,
                'name'        => $this->user->first_name . ' ' . $this->user->last_name,
                'image'       => optional($this->user->profile)->profile_image_url,
                'type'        => 'user',
                'roles'       => $this->user->roles->pluck('name'),
                'active_role' => $this->user->activeRole?->name,
            ];
        }

        return null;
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


    // public function shares()
    // {
    //     return $this->hasMany(ForumChatShare::class, 'forum_chat_id');
    // }
}
