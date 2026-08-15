<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // <--- 1. I-import ito

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; // <--- 2. Idagdag ang HasApiTokens dito

    protected $fillable = [
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
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