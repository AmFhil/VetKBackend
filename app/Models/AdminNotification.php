<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $table = 'admin_notifications';

    protected $fillable = [
        'admin_id', 'title', 'type', 'content', 'is_read', 'read_at', 'meta'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'meta' => 'array',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
