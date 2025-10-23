<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    //
    protected $guarded = [];

    public function member()
    {
        return $this->morphTo();
    }

    public function joinable()
    {
        return $this->morphTo();
    }
}
