<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One day of Google Search Console totals — see the create_gsc_daily_stats_table migration. */
class GscDailyStat extends Model
{
    public $timestamps = false;

    protected $fillable = ['stat_date', 'clicks', 'impressions', 'ctr', 'position', 'recorded_at'];

    protected $casts = [
        'stat_date' => 'date',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'float',
        'position' => 'float',
        'recorded_at' => 'datetime',
    ];
}
