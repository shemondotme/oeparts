<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A point-in-time disk measurement (total/free/used plus a per-category breakdown),
 * kept so the Cleanup page can show a growth trend. Pruned by the engine itself.
 *
 * @property int $id
 * @property Carbon $measured_at
 * @property int $total_bytes
 * @property int $free_bytes
 * @property int $used_bytes
 * @property array<string, int>|null $breakdown
 */
class CleanupDiskSample extends Model
{
    public $timestamps = false;

    protected $fillable = ['measured_at', 'total_bytes', 'free_bytes', 'used_bytes', 'breakdown'];

    protected $casts = [
        'measured_at' => 'datetime',
        'total_bytes' => 'integer',
        'free_bytes' => 'integer',
        'used_bytes' => 'integer',
        'breakdown' => 'array',
    ];
}
