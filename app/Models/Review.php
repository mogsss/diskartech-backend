<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $table = 'reviews';

    protected $fillable = [
        'application_id',
        'reviewer_id',
        'reviewee_id',
        'reviewer_role',
        'rating',
        'feedback',
        'tags',
    ];

    protected $casts = [
        'rating' => 'integer',
        'tags' => 'array',
    ];

    public function application()
    {
        return $this->belongsTo(JobApplication::class, 'application_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewee()
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }
}
