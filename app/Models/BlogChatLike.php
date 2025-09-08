<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogChatLike extends Model
{
    //

    public function user()
    {
        return $this->morphTo();
    }
}
