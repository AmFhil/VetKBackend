<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CaseModel extends Model
{
    protected $table = 'cases';

    protected $fillable = [
        'user_id',
        'case_id',
        'case_title',
        'client_name',
        'client_phone_number',
        'pet_or_farm',
        'pet_name',
        'specie',
        'breed',
        'pet_number',
        'farm_name',
        'type_of_livestock',
        'number_of_livestock',
        'number_of_workers',
        'age',
        'sex',
        'location',
        'other_details',
        'date_occurred',
        'date_presented',
        'history',
        'clinical_signs',
        'temperature',
        'heart_rate',
        'weight',
        'tentative_diagnosis',
        'differential_diagnosis',
        'lab_confirm',
        'mortality',
        'treatment_regimen',
        'picture'
    ];

    protected $casts = [
        'clinical_signs' => 'array',
        'treatment_regimen' => 'array',
        'date_occurred' => 'date',
        'date_presented' => 'date',
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

    public function comment()
    {
        return $this->hasMany(CaseComment::class, 'case_id');
    }
}
