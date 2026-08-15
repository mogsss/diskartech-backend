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
        'year_level',
        'contact_number',
        'contact_person',
        'age',              // <--- Idagdag ito
        'gender',           // <--- Idagdag ito
        'location',         // <--- Idagdag ito (para sa address)
        'detailed_address', // <--- Idagdag ito
        'latitude',
        'longitude',
        'isVerified',
        'status',
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