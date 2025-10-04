<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Model
{
    //
    use HasApiTokens, Notifiable, HasFactory;

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

    protected static function boot()
    {
        parent::boot();

        // Register the deleting event
        static::deleting(function ($admin) {
            // Delete all related blog_chats
            $admin->blogChats()->delete();
        });

        static::deleting(function ($admin) {
            // Delete all related blog_chats
            $admin->forumChats()->delete();
        });
    }

    protected $appends = ['profile_picture_url'];

    public function getProfilePictureUrlAttribute()
    {
        return $this->profile_picture ? Storage::url($this->profile_picture) : null;
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

    public function likedBlog()
    {
        return $this->belongsToMany(BlogChat::class, 'blogChatLikes', 'user_id', 'blog_chat_id');
    }

    public function forumChats()
    {
        return $this->morphMany(ForumChat::class, 'user');
    }

    public function forumChatComments()
    {
        return $this->morphMany(ForumChatComment::class, 'user');
    }

    public function forumChatLikes()
    {
        return $this->morphMany(ForumChatLike::class, 'user');
    }

    public function likedChats()
    {
        return $this->belongsToMany(ForumChat::class, 'forumChatLikes', 'user_id', 'forum_chat_id');
    }

    public function notifications()
    {
        return $this->hasMany(AdminNotification::class, 'admin_id');
    }

    public function activities()
    {
        return $this->hasMany(AdminActivity::class, 'admin_id');
    }
}
