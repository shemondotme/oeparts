<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * HealthCheckSnapshot — one row per health check per HealthCheckService::snapshot()
 * run. Append-only history backing the admin Health Check page's sparkline
 * trend and ok->fail transition detection (see HealthCheckService::snapshot()).
 */
class HealthCheckSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'check_key', 'status', 'detail', 'response_time_ms', 'checked_at',
    ];

    protected $casts = [
        'checked_at'       => 'datetime',
        'response_time_ms' => 'integer',
    ];

    public function scopeForCheck(Builder $query, string $key): Builder
    {
        return $query->where('check_key', $key);
    }
}
