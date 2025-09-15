<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class View extends Model
{
    protected $fillable = ['user_id', 'session_id', 'ip_address', 'user_agent'];

    public function viewable()
    {
        return $this->morphTo();
    }
}

