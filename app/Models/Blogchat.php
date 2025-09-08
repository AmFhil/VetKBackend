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
}