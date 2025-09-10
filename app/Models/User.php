<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users_registration';

    protected $primaryKey = 'id';

    public $incrementing = true;
    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'phone_num',
        'state',
        'country',
        'password',
        'password_algorithm',
        'provider',
        'provider_id',
        'old_id',
        'old_table'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function veterinaryDoctor()
    {
        return $this->hasOne(VeterinaryDoctor::class, 'user_id');
    }

    public function veterinaryParaprofessional()
    {
        return $this->hasOne(VeterinaryParaprofessional::class, 'user_id');
    }

    public function veterinaryClinic()
    {
        return $this->hasOne(VeterinaryClinic::class, 'user_id');
    }

    public function petOwner()
    {
        return $this->hasOne(Pet_Owner::class, 'user_id');
    }

    public function livestockFarmer()
    {
        return $this->hasOne(livestock_farmer::class, 'user_id');
    }

    public function others()
    {
        return $this->hasOne(Others::class, 'user_id');
    }

    public function vendor()
    {
        return $this->hasOne(Vendor::class, 'user_id');
    }

    public function profile()
    {
        return $this->hasOne(Profiles::class, 'user_id');
    }

    public function ratingsGiven()
    {
        return $this->hasMany(Rate::class, 'user_id');
    }

    public function ratingsReceived()
    {
        return $this->morphMany(Rate::class, 'rateable');
    }

    public function store()
    {
        return $this->hasMany(Store::class, 'user_id');
    }

    public function products()
    {
        return $this->hasMany(Products::class, 'user_id');
    }

    public function pet()
    {
        return $this->hasMany(Pet::class, 'user_id');
    }

    protected static function boot()
    {
        parent::boot();

        // Register the deleting event
        static::deleting(function ($user) {
            // Delete all related blog_chats
            $user->blogChats()->delete();
        });
    }

    public function blogChats()
    {
        return $this->morphMany(BlogChat::class, 'user');
    }
}
