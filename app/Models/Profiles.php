<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profiles extends Model
{
    //
    protected $table = 'profile';

    protected $fillable = [
        'bio',
        'profile_image',
        'cover_page_image'
    ];

    public function user(){
        $this->belongsTo(User::class, 'user_id');
    }
}
