<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Clinic extends Model
{
    //
    protected $table = "clinic";
    protected $primaryKey = 'id';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'license_number',
        'license_status',
        'clinic_name',
        'clinic_speciality' => 'array',
        'email',
        'phone_number',
        'location',
        'availability',
        'picture',
        'longitude',
        'latitude',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
