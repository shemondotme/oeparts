<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Cached size/age numbers for one cleanup target, so the Cleanup page never runs
 * COUNT(*) or walks a directory while rendering (the real catalog has 1M+ rows).
 * Refreshed by oeparts:cleanup:measure and by a per-row "Refresh" action; every
 * number is an ESTIMATE (information_schema on MySQL) and the UI labels it so.
 *
 * @property string $target
 * @property int|null $rows_est
 * @property int|null $bytes_est
 * @property Carbon|null $oldest_at
 * @property int|null $eligible_rows
 * @property int|null $eligible_bytes_est
 * @property Carbon|null $measured_at
 */
class CleanupTargetStat extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'target';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'target', 'rows_est', 'bytes_est', 'oldest_at',
        'eligible_rows', 'eligible_bytes_est', 'measured_at',
    ];

    protected $casts = [
        'rows_est' => 'integer',
        'bytes_est' => 'integer',
        'oldest_at' => 'datetime',
        'eligible_rows' => 'integer',
        'eligible_bytes_est' => 'integer',
        'measured_at' => 'datetime',
    ];
}
