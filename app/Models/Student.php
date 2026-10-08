<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_name',
        'student_school_name',
        'course',
        'student_schedule',
        'student_resume',
        'school_id',
        'coe',
        'skillset',
        'available_days',
        'time_slot',
        'year_level',
        'contact_number',
        'age',
        'gender',
        'location',
        'detailed_address',
        'latitude',
        'longitude',
        'isVerified',
        'rejection_reason',
        'school_id_ai_is_valid',
        'school_id_ai_remarks',
        'coe_ai_is_valid',
        'coe_ai_remarks',
        'avatar',
        'expo_push_token',
    ];

    // 👇 Idagdag ito para sa JSON casting ng available_days
    protected $casts = [
        'available_days' => 'array',
        'skillset' => 'array',
        'isVerified' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getDocsCountAttribute()
    {
        $documents = [
            $this->student_resume,
            $this->school_id,
            $this->coe,
        ];

        $uploadedCount = collect($documents)->filter()->count();
        $totalDocs = 3;

        return "{$uploadedCount} of {$totalDocs}";
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }
    public function savedJobs()
    {
        return $this->belongsToMany(Jobs::class, 'saved_jobs', 'student_id', 'job_id');
    }
}
