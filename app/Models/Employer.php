<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employer_name',
        'hirer_name',
        'employer_certificate_path',
        'valid_id_path',
        'contact_number',    // <--- Idinagdag para masave ang phone
        'location',
        'detailed_address',  // <--- Idinagdag para sa detailed address
        'latitude',
        'longitude',
        'isVerified',        // <--- Idinagdag para sa boolean verification
        'isSubscribed',      // <--- Idinagdag para sa subscription status
        'status',            // <--- Kasama na rin kung ginagamit mo ito
    ];
    public function getDocsCountAttribute()
        {
            $documents = [
                $this->employer_certificate_path,
                $this->valid_id_path,
            ];

            $uploadedCount = collect($documents)->filter()->count();
            $totalDocs = 2;
            return "{$uploadedCount} of {$totalDocs}";
        }
}