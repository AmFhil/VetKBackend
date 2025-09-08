<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogChat extends Model
{
    protected $fillable = [
        'user_id',
        'user_type',
        'slug',
        'title',
        'content',
        'category',
        'section',
        'picture',
        'status',
        'views_count',
        'shares_count',
        'comments_count',
        'likes_count',
    ];

    public function user()
    {
        return $this->morphTo();
    }

    public function blogChatComment()
    {
        return $this->hasMany(BlogChatComment::class, 'blog_chat_id');
    }

    public function blogChatLike()
    {
        return $this->hasMany(BlogChatLike::class, 'blog_chat_id');
    }

    protected static function booted()
    {
        static::created(function ($comment) {
            $comment->blog->increment('comments_count');
        });

        static::deleted(function ($comment) {
            $comment->blog->decrement('comments_count');
        });
    }
}
