<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Model
{
    //
    use HasApiTokens, Notifiable;

    protected $table = 'admin';

    protected $primaryKey = 'id';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'staff_id',
        'password',
        'permission_level',
        'email',
        'first_name',
        'last_name',
        'phone_number',
        'location',
        'profile_picture',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

}
