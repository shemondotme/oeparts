<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UpdateHistory (Module 21, Chunk 3.5) — one row per in-app update attempt; also
 * the FSM's checkpoint (status + step + meta.step_index) so a poll-driven apply
 * resumes after the browser closes.
 *
 * @property array|null $meta
 */
class UpdateHistory extends Model
{
    use HasFactory;

    // Granular statuses (mirror the migration comment).
    public const STATUS_PENDING     = 'pending';
    public const STATUS_BACKING_UP  = 'backing_up';
    public const STATUS_DOWNLOADING = 'downloading';
    public const STATUS_EXTRACTING  = 'extracting';
    public const STATUS_SWAPPING    = 'swapping';
    public const STATUS_MIGRATING   = 'migrating';
    public const STATUS_FINALIZING  = 'finalizing';
    public const STATUS_SUCCESS     = 'success';
    public const STATUS_FAILED      = 'failed';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'from_version', 'to_version', 'channel', 'status', 'step',
        'initiated_by', 'backup_run_id', 'started_at', 'finished_at', 'error', 'meta',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'meta'        => 'array',
    ];

    public function backupRun(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class, 'backup_run_id');
    }

    /** The target release manifest, stashed at start(). */
    public function manifest(): array
    {
        return (array) ($this->meta['manifest'] ?? []);
    }

    public function stepIndex(): int
    {
        return (int) ($this->meta['step_index'] ?? 0);
    }

    public function setStepIndex(int $index): void
    {
        $meta = $this->meta ?? [];
        $meta['step_index'] = $index;
        $this->meta = $meta;
    }

    public function putMeta(string $key, mixed $value): void
    {
        $meta = $this->meta ?? [];
        $meta[$key] = $value;
        $this->meta = $meta;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_ROLLED_BACK,
        ], true);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function scopeRecent(Builder $q): Builder
    {
        return $q->orderByDesc('id');
    }
}
