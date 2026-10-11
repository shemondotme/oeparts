<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The per-target numbers of one CleanupRun.
 *
 * Reconciliation identity (what makes "100% successful" provable, not just claimed):
 *   targeted = deleted + external_gone + remaining + skipped
 * `external_gone` are rows another process removed meanwhile, `late_rows` are rows
 * that started qualifying after the run began (informational, not part of the sum).
 *
 * @property int $id
 * @property int $run_id
 * @property string $target
 * @property string $status
 * @property Carbon|null $cutoff
 * @property int|null $retention_days
 * @property int $targeted
 * @property int $deleted
 * @property int $failed
 * @property int $remaining
 * @property int $external_gone
 * @property int $late_rows
 * @property int|null $bytes_freed_apparent
 * @property int|null $bytes_freed_allocated
 * @property int|null $fs_free_before
 * @property int|null $fs_free_after
 * @property string|null $archive_path
 * @property int|null $archive_rows
 * @property list<string>|null $reasons
 * @property array<string, mixed>|null $checkpoint
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class CleanupRunItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /** Skipped before deleting anything: the over-delete guard tripped. */
    public const STATUS_SKIPPED_GUARD = 'skipped_guard';

    /** Skipped: the effective retention would be below the target's floor. */
    public const STATUS_SKIPPED_FLOOR = 'skipped_floor';

    /** Skipped: a backup/update/restore was running. */
    public const STATUS_BLOCKED_BUSY = 'blocked_busy';

    protected $fillable = [
        'run_id', 'target', 'status', 'cutoff', 'retention_days',
        'targeted', 'deleted', 'failed', 'remaining', 'external_gone', 'late_rows',
        'bytes_freed_apparent', 'bytes_freed_allocated', 'fs_free_before', 'fs_free_after',
        'archive_path', 'archive_rows', 'reasons', 'checkpoint', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'cutoff' => 'datetime',
        'retention_days' => 'integer',
        'targeted' => 'integer',
        'deleted' => 'integer',
        'failed' => 'integer',
        'remaining' => 'integer',
        'external_gone' => 'integer',
        'late_rows' => 'integer',
        'bytes_freed_apparent' => 'integer',
        'bytes_freed_allocated' => 'integer',
        'fs_free_before' => 'integer',
        'fs_free_after' => 'integer',
        'archive_rows' => 'integer',
        'reasons' => 'array',
        'checkpoint' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(CleanupRun::class, 'run_id');
    }
}
