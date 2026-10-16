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

    protected static function booted(): void
    {
        // Anonymized account placeholders preserve shared work/payment records,
        // but must never resolve through login, Sanctum, or public user queries.
        static::addGlobalScope('active-account', fn ($query) => $query->where($query->getModel()->qualifyColumn('role'), '<>', 'deleted'));
    }

    protected $fillable = [
        'email',
        'password',
        'role',
        'registered_with_google',
        'isEmailVerified',     
        'otp_code',            
        'otp_expires_at',      
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',            
        'otp_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'registered_with_google' => 'boolean',
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
