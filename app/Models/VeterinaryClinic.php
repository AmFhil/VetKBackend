<?php

namespace App\Models;

use App\Traits\HasViews;
use Illuminate\Database\Eloquent\Model;

class VeterinaryClinic extends Model
{
    use HasViews;

    //
    protected $table = 'veterinary_clinic';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'practice_license_num',
        'name_of_clinic',
        'specialty',
        'list_them',
        'contact_num',
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
        return $this->morphMany(Rate::class, 'rateable');
    }

    public function averageRating()
    {
        return round($this->ratings()->avg('rating') ?? 0, 1);
    }
}
