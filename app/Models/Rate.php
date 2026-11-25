<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    //
    protected $table = 'rating';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'rateable_id',
        'rateable_type',
        'rating',
        'comment',
    ];

    protected $hidden = [
        'rateable_type'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    public function rateable()
    {
        return $this->morphTo();
    }

    //
}
