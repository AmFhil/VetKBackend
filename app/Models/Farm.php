<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Farm extends Model
{
    protected $table = "farm";
    protected $primaryKey = 'id';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'farm_name',
        'farm_id',
        'location',
        'no_of_worker',  
        'livestock_type',
        'no_of_livestock',
        'sex',
        'age',
        'picture',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
