<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'household_name',
        'cp_number',     
        'age',            
        'gender',        
        'location',
        'detailed_address',
        'latitude',
        'longitude',
        'isVerified',
        'valid_id_path',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getDocsCountAttribute()
{
    $documents = [
        $this->valid_id_path,
    ];

    $uploadedCount = collect($documents)->filter()->count();
    $totalDocs = 1; // 1 lang dahil valid_id_path lang ang kailangan nila

    return "{$uploadedCount} of {$totalDocs}";
}
    public function jobs()
    {
        return $this->hasMany(AvailableJob::class);
    }
}