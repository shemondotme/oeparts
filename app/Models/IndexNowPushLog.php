<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per PushIndexNow job attempt — success/failure counts and last-run
 * time can't be reconstructed from Filament's transient bell notifications
 * alone, which is the only other place that outcome is currently surfaced.
 */
class IndexNowPushLog extends Model
{
    protected $table = 'indexnow_push_logs';

    public $timestamps = false;

    protected $fillable = [
        'url_count', 'status', 'error_message', 'created_at',
    ];

    protected $casts = [
        'url_count' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (IndexNowPushLog $log) {
            $log->created_at ??= now();
        });
    }
}
