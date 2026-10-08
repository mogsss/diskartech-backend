<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jobs extends Model
{
    use HasFactory;

    protected $table = 'available_jobs';
    protected $guarded = [];

    protected $casts = [
        'available_days' => 'array',
        'requirements' => 'array',
        'skills' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

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
            "available_jobs.*, (6371 * acos(cos(radians(?)) * cos(radians(available_jobs.latitude)) * cos(radians(available_jobs.longitude) - radians(?)) + sin(radians(?)) * sin(radians(available_jobs.latitude)))) AS distance",
            [$lat, $long, $lat]
        );
    }
    public function applications()
{
    // 'job_id' ang foreign key sa job_applications table
    return $this->hasMany(JobApplication::class, 'job_id');
}
}