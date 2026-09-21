<?php

namespace App\Models;

use App\Enums\LogStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CronLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'job_name', 'status', 'duration_ms', 'output', 'ran_at',
    ];

    protected $casts = [
        'status' => LogStatus::class,
        'ran_at' => 'datetime',
    ];

    public function scopeFailed(Builder $q): Builder
    {
        return $q->where('status', LogStatus::Failed->value);
    }
}
