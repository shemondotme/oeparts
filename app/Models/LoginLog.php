<?php

namespace App\Models;

use App\Enums\LoginUserType;
use App\Enums\LogStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_type', 'email', 'status', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'user_type' => LoginUserType::class,
        'status'    => LogStatus::class,
    ];
}
