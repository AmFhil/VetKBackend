<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    //
    protected $table = 'medias';

    protected $fillable = [
        'user_id',
        'file_path',
        'type',
    ];

    protected $appends = ['file_url'];

    public function getFileUrlAttribute()
    {
        return Storage::url($this->file_path);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


}
