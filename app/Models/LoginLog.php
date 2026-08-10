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
        'created_at' => 'datetime',
    ];

    /**
     * Same shape/bug as ActivityLog/SearchLog/FailedSearchLog: created_at is
     * nullable with no default and $timestamps = false, so every row
     * silently persisted a NULL created_at — the admin Login Logs screen's
     * default sort depends on it.
     */
    protected static function booted(): void
    {
        static::creating(function (LoginLog $log) {
            $log->created_at ??= now();
        });
    }
}
