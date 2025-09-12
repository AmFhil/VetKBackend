<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CaseModel extends Model
{
    protected $table = 'cases';

    protected $fillable = [
        'user_id','case_id','case_title','client_name','client_phone_number','pet_or_farm',
        'pet_name','specie','breed','pet_number','farm_name','type_of_livestock',
        'number_of_livestock','number_of_workers','age','sex','location','other_details',
        'date_occurred','history','clinical_signs','tentative_diagnosis','differential_diagnosis',
        'disease_diagnosis','lab_confirm','mortality','treatment_regimen','clinic_physical_address',
        'mobile_veterinarian','picture'
    ];

    protected $casts = [
        'clinical_signs' => 'array',
        'treatment_regimen' => 'array',
        'date_occurred' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Auto URL for picture
    protected $appends = ['picture_url'];

    public function getPictureUrlAttribute()
    {
        return $this->picture ? Storage::url($this->picture) : null;
    }
}
