<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jobs extends Model
{
    use HasFactory;

    protected $table = 'available_jobs';
    protected $guarded = [];

    public function household()
    {
        return $this->belongsTo(Household::class, 'user_id', 'user_id');
    }

    public function employer()
    {
        return $this->belongsTo(Employer::class, 'user_id', 'user_id');
    }

    // 👇 IDINAGDAG NA MODEL SCOPE PARA SA DISTANCE (Haversine Formula)
    public function scopeWithDistance($query, $lat, $long)
    {
        return $query->selectRaw(
            "*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
            [$lat, $long, $lat]
        );
    }
    public function applications()
{
    // 'job_id' ang foreign key sa job_applications table
    return $this->hasMany(JobApplication::class, 'job_id');
}
}