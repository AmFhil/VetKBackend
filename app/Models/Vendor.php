<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    //
    protected $table = 'vendor';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;

    
    protected $fillable = [
        'user_id',
        'role',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
