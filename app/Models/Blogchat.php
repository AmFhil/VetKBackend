<?php

namespace App\Models;

use App\Traits\HasAuthorAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class BlogChat extends Model
{
    use SoftDeletes, HasAuthorAttribute;

    protected $table = 'blog_chats';

    protected $fillable = [
        'title',
        'content',
        'user_id',
        'user_type',
        'slug',
        'category',
        'section',
        'status',
        'picture',
        'likes_count',
        'views_count',
        'shares_count',
        'comments_count',
    ];

    protected $appends = ['author'];

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

    // get picture url
    public function getPictureAttribute(){
        return $this->picture ? Storage::url($this->picture) : null;
    }

    public function user()
    {
        return $this->morphTo();
    }

    public function comments()
    {
        return $this->hasMany(BlogChatComment::class, 'blog_chat_id');
    }

    public function likes()
    {
        return $this->hasMany(BlogChatLike::class, 'blog_chat_id');
    }
}
