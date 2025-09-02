<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VeterinaryDoctor extends Model
{
    //
    protected $table = 'veterinary_doctor';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'practice_license_num',
        'specialty',
        'list_them',
        'address',
        'longitude',
        'latitude',
        'role',
        'is_approved',
        'availability',
        'view'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ratings()
    {
        return $this->hasMany(Rate::class, 'rated_user_id');
    }

    //
}
