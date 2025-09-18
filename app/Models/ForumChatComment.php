<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumChatComment extends Model
{
    //
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
        });

        static::deleted(function ($comment) {
            $comment->forum->decrement('comments_count');
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
        return $this->hasMany(ForumChatComment::class, 'parent_id')
            ->with('replies');
    }

    public function user()
    {
        return $this->morphTo();
    }
}
