<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseComment extends Model
{
    //
    protected $table = 'case_comment';
    protected $primaryKey = 'id';

    protected $fillable = [
        'case_id',
        'user_id',
        'parent_id',
        'comment',
    ];

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent()
    {
        return $this->belongsTo(CaseComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(CaseComment::class, 'parent_id');
    }
}
