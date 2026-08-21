<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    use HasFactory;

    protected $table = 'job_postings';
    protected $guarded = [];

    public function household()
    {
        return $this->belongsTo(Household::class, 'user_id', 'user_id');
    }

    public function employer()
    {
        return $this->belongsTo(Employer::class, 'user_id', 'user_id');
    }
}