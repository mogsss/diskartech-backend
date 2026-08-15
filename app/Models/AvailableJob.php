<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvailableJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'employer_id',
        'household_id',
        'job_name',
        'job_description',
        'job_location',
        'job_schedule',
        'rate',
        'span',
        'latitude',
        'longitude',
    ];

    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class, 'job_id');
    }
}