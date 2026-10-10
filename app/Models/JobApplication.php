<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'student_id',
        'status',
        'interview_date',
        'interview_time',
        'interview_location',
        'interview_type',
        'interview_ended_at',
        'start_date',
        'end_date',
        'work_schedule',
        'agreed_rate',
        'special_instructions',
        'contract_terms',
        'hired_at',
        'terminated_at',
        'termination_reason',
        'certificate_status',
        'certificate_requested_at',
        'certificate_issued_at',
        'employer_signature',
    ];

    protected function casts(): array
    {
        return ['interview_ended_at' => 'datetime'];
    }

    public function job()
    {
        // Pinapalitan natin ng Jobs::class para magtugma sa iyong Jobs model
        return $this->belongsTo(Jobs::class, 'job_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'application_id');
    }
}
