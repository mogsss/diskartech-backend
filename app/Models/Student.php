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
        'contact_person',
        'age',            
        'gender',           
        'location',        
        'detailed_address', 
        'latitude',
        'longitude',
        'isVerified',
        'status',
        'avatar'
    ];

    // 👇 Idagdag ito para sa JSON casting ng available_days
    protected $casts = [
        'available_days' => 'array',
        'skillset' => 'array',
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
}