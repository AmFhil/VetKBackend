<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DirectMessage extends Model
{
    use SoftDeletes;

    protected $table = 'direct_messages';

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'content',
        'image',
        'is_read',
        'is_flagged',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_flagged' => 'boolean',
        'image' => 'array', // since it's stored as JSON
    ];

    // Relationships
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
