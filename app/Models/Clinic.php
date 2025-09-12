<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    protected $appends = ['picture_url'];

    public function getPictureUrlAttribute()
    {
        return $this->picture ? Storage::url($this->picture) : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
