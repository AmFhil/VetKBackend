<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Others extends Model
{
    //
    protected $table = 'others';
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
}
