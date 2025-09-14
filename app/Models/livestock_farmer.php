<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class livestock_farmer extends Model
{
    //
    protected $table = 'livestock_farmer';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'role',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ratings()
    {
        return $this->morphMany(Rate::class, 'rateable');
    }

    public function averageRating()
    {
        return round($this->ratings()->avg('rating') ?? 0, 1);
    }
}
