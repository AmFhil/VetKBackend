<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogChatComment extends Model
{
    //

    public function user()
    {
        return $this->morphTo();
    }
}
