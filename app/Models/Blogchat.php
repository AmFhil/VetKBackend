<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogChat extends Model
{
    protected $table = 'blog_chats';

    protected $fillable = [
        'title',
        'content',
        'user_id',
        'user_type',
        'likes_count',
        'comments_count',
    ];

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
                'id'    => $this->user->id,
                'name'  => $this->user->first_name . ' ' . $this->user->last_name,
                'image' => optional($this->user->profile)->profile_image_url,
                'type'  => 'user',
            ];
        }

        return null;
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
