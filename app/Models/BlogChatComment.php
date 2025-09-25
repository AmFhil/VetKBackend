<?php

namespace App\Models;

use App\Traits\HasAuthorAttribute;
use Illuminate\Database\Eloquent\Model;

class BlogChatComment extends Model
{
    use HasAuthorAttribute;

    protected $table = 'blog_chat_comments';

    protected $fillable = [
        'blog_chat_id',
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
            $comment->blog->increment('comments_count');
        });

        static::deleted(function ($comment) {
            $comment->blog->decrement('comments_count');
        });
    }


    public function blog()
    {
        return $this->belongsTo(BlogChat::class, 'blog_chat_id');
    }

    public function parent()
    {
        return $this->belongsTo(BlogChatComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(BlogChatComment::class, 'parent_id')
            ->with('replies');
    }

    public function user()
    {
        return $this->morphTo();
    }
}
