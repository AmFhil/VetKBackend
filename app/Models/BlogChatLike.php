<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogChatLike extends Model
{
    protected $table = 'blog_chat_likes';

    protected $fillable = [
        'blog_chat_id',
        'user_id',
        'user_type',
    ];

    protected $appends = ['author'];

    protected static function booted()
    {
        static::created(function ($like) {
            $like->blog->increment('likes_count');
        });

        static::deleted(function ($like) {
            $like->blog->decrement('likes_count');
        });
    }

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
                'id'    => $this->user->id,
                'name'  => $this->user->first_name . ' ' . $this->user->last_name,
                'image' => optional($this->user->profile)->profile_image_url,
                'type'  => 'user',
            ];
        }

        return null;
    }

    public function blog()
    {
        return $this->belongsTo(BlogChat::class, 'blog_chat_id');
    }

    public function user()
    {
        return $this->morphTo();
    }
}
