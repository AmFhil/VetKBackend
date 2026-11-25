<?php

namespace App\Models;

use App\Traits\HasViews;
use Illuminate\Database\Eloquent\Model;

class VeterinaryParaprofessional extends Model
{
    use HasViews;
    //
    protected $table = 'veterinary_paraprofessional';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'name_of_institution',
        'graduation_year',
        'expected_year_of_graduation',
        'specialty',
        'list_them',
        'contact_num',
        'address',
        'longitude',
        'latitude',
        'role',
        'is_approved',
        'availability',
        'view'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ratings()
    {
        return $this->morphMany(Rate::class, 'rateable');
    }

    public function averageRating()
    {
        return round($this->ratings()->avg('rating') ?? 0, 1);
    }

    public function scopeNearest($query, float $latitude, float $longitude)
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            $lat = (float) $latitude;
            $lon = (float) $longitude;
            return $query->select('veterinary_paraprofessional.*')
                ->selectRaw("((CAST(latitude AS FLOAT) - $lat)*(CAST(latitude AS FLOAT) - $lat) + (CAST(longitude AS FLOAT) - $lon)*(CAST(longitude AS FLOAT) - $lon)) as distance")
                ->orderBy('distance', 'asc');
        }

        $haversine = "(6371 * acos(cos(radians(?))
                    * cos(radians(latitude))
                    * cos(radians(longitude) - radians(?))
                    + sin(radians(?))
                    * sin(radians(latitude))))";

        return $query->select('veterinary_paraprofessional.*')
            ->selectRaw("$haversine as distance", [$latitude, $longitude, $latitude])
            ->orderBy('distance', 'asc');
    }
}
