<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VeterinaryParaprofessional extends Model
{
    //
    protected $table = 'veterinary_paraprofessional';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    
    protected $fillable = [
        'user_id',
        'name_of_institution',
        'graduation_year',
        'expected_year_of_graduation',
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
        return $this->hasMany(Rate::class, 'rated_user_id');
    }


}
