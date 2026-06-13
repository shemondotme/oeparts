<?php

namespace App\Models;

use App\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'email', 'otp_code', 'purpose',
        'expires_at', 'verified_at', 'attempts', 'ip_address',
    ];

    protected $casts = [
        'purpose'     => OtpPurpose::class,
        'expires_at'  => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
