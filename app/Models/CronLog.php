<?php

namespace App\Models;

use App\Enums\LogStatus;
use Illuminate\Database\Eloquent\Model;

class CronLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'job_name', 'status', 'duration_ms', 'output', 'ran_at',
    ];

    protected $casts = [
        'status' => LogStatus::class,
        'ran_at' => 'datetime',
    ];
}
