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
    ];

    public function job()
    {
        // Pinapalitan natin ng Jobs::class para magtugma sa iyong Jobs model
        return $this->belongsTo(Jobs::class, 'job_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}