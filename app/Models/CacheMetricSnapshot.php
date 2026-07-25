<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CacheMetricSnapshot — one row per CacheMetricsService::snapshot() run.
 * Append-only history backing the Cache Dashboard's Server Health sparklines.
 */
class CacheMetricSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'hit_rate', 'memory_used_bytes', 'memory_max_bytes', 'fragmentation_ratio',
        'evicted_keys', 'ops_per_sec', 'total_keys', 'recorded_at',
    ];

    protected $casts = [
        'hit_rate'            => 'integer',
        'memory_used_bytes'   => 'integer',
        'memory_max_bytes'    => 'integer',
        'fragmentation_ratio' => 'float',
        'evicted_keys'        => 'integer',
        'ops_per_sec'         => 'integer',
        'total_keys'          => 'integer',
        'recorded_at'         => 'datetime',
    ];
}
