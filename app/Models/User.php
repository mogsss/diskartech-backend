<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail; // 👈 Pwede na nating i-comment out
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable // 👈 Tinanggal na ang implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password',
        'role',
        'isEmailVerified',     
        'otp_code',            
        'otp_expires_at',      
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',            
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'isEmailVerified' => 'boolean',     
            'otp_expires_at' => 'datetime',     
        ];
    }

    // Relationships
    public function studentProfile()
    {
        return $this->hasOne(Student::class);
    }

    public function employerProfile()
    {
        return $this->hasOne(Employer::class);
    }

    public function householdProfile()
    {
        return $this->hasOne(Household::class);
    }
}