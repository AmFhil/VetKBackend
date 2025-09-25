<?php

namespace App\Models;

use App\Traits\HasAuthorAttribute;
use Illuminate\Database\Eloquent\Model;

class BlogChatLike extends Model
{
    use HasAuthorAttribute;

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


    public function blog()
    {
        return $this->belongsTo(BlogChat::class, 'blog_chat_id');
    }

    public function user()
    {
        return $this->morphTo();
    }
}
