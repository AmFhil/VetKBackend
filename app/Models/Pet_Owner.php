<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pet_Owner extends Model
{
    //
    protected $table = 'pet_owners';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

        /**
        * The attributes that are mass assignable.
        *
        * @var array<int, string>
        */
    protected $fillable = [
        'user_id',
        'role',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // optional: allow ratings if you plan to
    public function ratings()
    {
        return $this->morphMany(Rate::class, 'rateable');
    }

    public function averageRating()
    {
        return round($this->ratings()->avg('rating') ?? 0, 1);
    }
}
