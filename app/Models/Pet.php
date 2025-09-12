<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Pet extends Model
{
    //
    protected $table = "pet";
    protected $primaryKey = 'id';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'pet_name',
        'pet_id',
        'specie',
        'breed',
        'sex',
        'age',
        'picture',
    ];

    protected $appends = ['picture_url'];

    public function getPictureUrlAttribute()
    {
        return $this->picture ? Storage::url($this->picture) : null;
    }

    public function user(){
       return $this->belongsTo(User::class, 'user_id');
    }
}
