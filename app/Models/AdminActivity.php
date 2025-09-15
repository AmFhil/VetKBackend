<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivity extends Model
{
    protected $table = 'admin_activities';

    protected $fillable = [
        'admin_id', 'action', 'title', 'detail', 'meta'
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
