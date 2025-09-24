<?php

namespace App\Models;

use App\Traits\HasUserEvents;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUserEvents;

    protected $table = 'users_registration';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

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
        'old_table',
        'active_role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'provider_id',
        'password_algorithm',
        'old_id',
        'old_table',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Many roles
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    // Active role (from roles table)
    public function activeRole()
    {
        return $this->belongsTo(Role::class, 'active_role_id');
    }

    public function hasRole($role)
    {
        return $this->roles()->where('name', $role)->exists();
    }

    // --- Role-specific relationships ---
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
        return $this->hasOne(Livestock_Farmer::class, 'user_id');
    }

    public function others()
    {
        return $this->hasOne(Others::class, 'user_id');
    }

    public function vendor()
    {
        return $this->hasOne(Vendor::class, 'user_id');
    }

    // --- Active role data (dynamic switch) ---
    public function activeRoleModel()
    {
        return match ($this->activeRole?->name) {
            'pet_owner' => $this->petOwner(),
            'vendor' => $this->vendor(),
            'veterinary_doctor' => $this->veterinaryDoctor(),
            'veterinary_clinic' => $this->veterinaryClinic(),
            'veterinary_paraprofessional' => $this->veterinaryParaprofessional(),
            'livestock_farmer' => $this->livestockFarmer(),
            'others' => $this->others(),
            default => null,
        };
    }

    // Example: $user->activeRoleData
    public function getActiveRoleDataAttribute()
    {
        return $this->activeRoleModel()?->first();
    }

    // --- Other relationships (unchanged) ---
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

    public function farm()
    {
        return $this->hasMany(Farm::class, 'user_id');
    }

    public function clinic()
    {
        return $this->hasMany(Clinic::class, 'user_id');
    }

    public function cases()
    {
        return $this->hasMany(CaseModel::class, 'user_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($user) {
            $user->blogChats()->delete();
            $user->forumChats()->delete();
            $user->blogChatComments()->delete();
            $user->forumChatComments()->delete();
            $user->blogChatLikes()->delete();
            $user->forumChatLikes()->delete();
            $user->notifications()->delete();
            $user->activities()->delete();
            $user->store()->delete();
            $user->products()->delete();
            $user->pet()->delete();
            $user->farm()->delete();
            $user->clinic()->delete();
            $user->cases()->delete();
            $user->ratingsGiven()->delete();
            $user->ratingsReceived()->delete();
            $user->subscription()->delete();
            $user->sentMessages()->delete();
            $user->receivedMessages()->delete();
        });


    }

    public function blogChats()
    {
        return $this->morphMany(BlogChat::class, 'user');
    }

    public function blogChatComments()
    {
        return $this->morphMany(BlogChatComment::class, 'user');
    }

    public function blogChatLikes()
    {
        return $this->morphMany(BlogChatLike::class, 'user');
    }

    public function forumChats(){
        return $this->morphMany(ForumChat::class, 'user');
    }

    public function forumChatComments(){
        return $this->morphMany(ForumChatComment::class, 'user');
    }

    public function forumChatLikes(){
        return $this->morphMany(ForumChatLike::class, 'user');
    }

    // for subscriptions
    public function subscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class, 'user_id')->latestOfMany();
    }

    public function canCreateStore(): bool
    {
        $sub = $this->subscription;
        if (!$sub) return false;

        $limits = $sub->store; // casted JSON
        $maxStores = $limits['stores'] ?? null;

        $currentStores = Store::where('user_id', $this->id)->count();

        return is_null($maxStores) || $currentStores < $maxStores;
    }

    public function canAddProduct($storeId): bool
    {
        $sub = $this->subscription;
        if (!$sub) return false;

        $limits = $sub->store;
        $maxProducts = $limits['products_per_store'] ?? null;

        $currentProducts = Products::where('store_id', $storeId)->count();

        return is_null($maxProducts) || $currentProducts < $maxProducts;
    }

    public function canCreateCase(): bool
    {
        $sub = $this->subscription;
        if (!$sub) return false;

        $limit = $sub->case_record;

        $currentCases = CaseModel::where('user_id', $this->id)->count();

        // if null => unlimited
        return is_null($limit) || $currentCases < $limit;
    }

    // for notification and activity
    public function notifications()
    {
        return $this->hasMany(UserNotification::class, 'user_id');
    }

    public function activities()
    {
        return $this->hasMany(UserActivity::class, 'user_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(DirectMessage::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(DirectMessage::class, 'receiver_id');
    }
}
