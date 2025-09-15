<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $table = 'user_notifications';
    protected $fillable = ['user_id', 'title', 'type', 'content', 'is_read', 'read_at', 'meta'];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

