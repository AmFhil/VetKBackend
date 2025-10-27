<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiseasePredictor extends Model
{
    //

    protected $table = 'disease_predictors';

    protected $fillable = [
        'specie',
        'symptoms',
        'description',
        'image',
    ];

    protected $casts = [
        'symptoms' => 'array',
    ];
}
