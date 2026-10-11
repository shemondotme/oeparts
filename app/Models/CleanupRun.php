<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One Cleanup Engine run (a single target, or a scheduled batch of many).
 *
 * `updated_at` is the heartbeat: the manager saves the row on every advance(), so
 * a run whose updated_at stops moving is stuck and the stale janitor reclaims it.
 * `active_key` is 'cleanup' only while the run is non-terminal; the column's UNIQUE
 * index is what guarantees two cleanups can never run at once.
 *
 * @property int $id
 * @property string $kind
 * @property string|null $target
 * @property string $mode
 * @property string $trigger
 * @property string $status
 * @property string|null $step
 * @property int $percent
 * @property string|null $message
 * @property int $targeted
 * @property int $deleted
 * @property int $failed
 * @property int $remaining
 * @property int|null $bytes_before
 * @property int|null $bytes_after
 * @property int|null $bytes_freed
 * @property Carbon|null $cutoff
 * @property int|null $admin_id
 * @property int|null $resumed_from_id
 * @property int|null $preview_run_id
 * @property Carbon|null $cancel_requested_at
 * @property string|null $paused_reason
 * @property string|null $error
 * @property list<string>|null $reasons
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property string|null $active_key
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CleanupRun extends Model
{
    public const KIND_SINGLE = 'single';

    public const KIND_BATCH = 'batch';

    public const MODE_DRY_RUN = 'dry_run';

    public const MODE_EXECUTE = 'execute';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_SCHEDULED = 'scheduled';

    public const TRIGGER_RESUME = 'resume';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    /** Waiting, not failed: the system was busy or the auto window closed. It resumes by itself. */
    public const STATUS_PAUSED = 'paused';

    public const STATUS_SUCCESS = 'success';

    /** Some rows/files remain, failed, or an item was skipped by a guard. */
    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /** The value of active_key while a run is non-terminal. */
    public const ACTIVE_KEY = 'cleanup';

    public const TERMINAL_STATUSES = [
        self::STATUS_SUCCESS,
        self::STATUS_PARTIAL,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'kind', 'target', 'mode', 'trigger', 'status', 'step', 'percent', 'message',
        'targeted', 'deleted', 'failed', 'remaining',
        'bytes_before', 'bytes_after', 'bytes_freed',
        'cutoff', 'admin_id', 'resumed_from_id', 'preview_run_id',
        'cancel_requested_at', 'paused_reason', 'error', 'reasons', 'meta',
        'started_at', 'finished_at', 'active_key',
    ];

    protected $casts = [
        'percent' => 'integer',
        'targeted' => 'integer',
        'deleted' => 'integer',
        'failed' => 'integer',
        'remaining' => 'integer',
        'bytes_before' => 'integer',
        'bytes_after' => 'integer',
        'bytes_freed' => 'integer',
        'cutoff' => 'datetime',
        'cancel_requested_at' => 'datetime',
        'reasons' => 'array',
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** @return HasMany<CleanupRunItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CleanupRunItem::class, 'run_id');
    }

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /* ---- Lifecycle helpers ---------------------------------------------- */

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isDryRun(): bool
    {
        return $this->mode === self::MODE_DRY_RUN;
    }

    public function isCancelRequested(): bool
    {
        return $this->cancel_requested_at !== null;
    }

    /** A stable owner token for the shared backup/update lock (file-family targets take it). */
    public function lockOwner(): string
    {
        return 'cleanup:'.$this->getKey();
    }

    /* ---- Resumable checkpoint (stored inside meta) ---------------------- */

    /** @return array<string, mixed> */
    public function checkpoint(): array
    {
        return (array) ($this->meta['checkpoint'] ?? []);
    }

    /** @param  array<string, mixed>  $checkpoint */
    public function setCheckpoint(array $checkpoint): void
    {
        $meta = $this->meta ?? [];
        $meta['checkpoint'] = $checkpoint;
        $this->meta = $meta; // reassign so Eloquent marks the JSON attribute dirty
    }

    public function getMetaValue(string $key, mixed $default = null): mixed
    {
        return ($this->meta ?? [])[$key] ?? $default;
    }

    public function putMeta(string $key, mixed $value): void
    {
        $meta = $this->meta ?? [];
        $meta[$key] = $value;
        $this->meta = $meta;
    }

    /* ---- Scopes --------------------------------------------------------- */

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNotIn('status', self::TERMINAL_STATUSES);
    }

    public function scopeTerminal(Builder $q): Builder
    {
        return $q->whereIn('status', self::TERMINAL_STATUSES);
    }
}
