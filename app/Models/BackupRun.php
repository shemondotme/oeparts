<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BackupRun (Module 14/21, Chunk 2.1) — one row per backup run.
 *
 * Drives the chunked, resumable Backup Engine FSM. `status` is the coarse
 * lifecycle (pending → running → success|failed); the fine-grained, resumable
 * checkpoint (which stage, which cursor) lives in `meta['checkpoint']` so a
 * browser-closed / crashed run can pick up exactly where it stopped.
 *
 * @property array|null $meta
 */
class BackupRun extends Model
{
    use HasFactory;

    /** Profiles. */
    public const PROFILE_UPDATE_SAFETY  = 'update_safety';  // slim, taken just before an update
    public const PROFILE_FULL           = 'full';           // full disaster-recovery backup (db + files)
    public const PROFILE_DATABASE_ONLY  = 'database_only';  // db only, admin-triggered
    public const PROFILE_FILES_ONLY     = 'files_only';     // files only, admin-triggered

    /** Statuses. */
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    /** Triggers. */
    public const TRIGGER_MANUAL     = 'manual';
    public const TRIGGER_SCHEDULED  = 'scheduled';
    public const TRIGGER_PRE_UPDATE = 'pre_update';

    protected $fillable = [
        'profile', 'status', 'trigger', 'disk', 'encrypted',
        'app_version', 'php_version', 'db_version',
        'total_bytes', 'part_count', 'manifest_path', 'checksum',
        'started_at', 'finished_at', 'expires_at', 'error', 'meta',
    ];

    protected $casts = [
        'encrypted'   => 'boolean',
        'total_bytes' => 'integer',
        'part_count'  => 'integer',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'expires_at'  => 'datetime',
        'meta'        => 'array',
    ];

    public function parts(): HasMany
    {
        return $this->hasMany(BackupPart::class);
    }

    /* ---- Lifecycle helpers ---------------------------------------------- */

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCESS, self::STATUS_FAILED], true);
    }

    /** A stable owner token for the shared update/backup lock. */
    public function lockOwner(): string
    {
        return 'backup:'.$this->getKey();
    }

    /* ---- Resumable checkpoint (stored inside meta) ---------------------- */

    /** @return array{stage_index:int,stage_state:array} */
    public function checkpoint(): array
    {
        $cp = (array) ($this->meta['checkpoint'] ?? []);

        return [
            'stage_index' => (int) ($cp['stage_index'] ?? 0),
            'stage_state' => (array) ($cp['stage_state'] ?? []),
        ];
    }

    public function setCheckpoint(array $checkpoint): void
    {
        $meta = $this->meta ?? [];
        $meta['checkpoint'] = $checkpoint;
        $this->meta = $meta; // reassign so Eloquent marks the JSON attribute dirty
    }

    public function clearCheckpoint(): void
    {
        $meta = $this->meta ?? [];
        unset($meta['checkpoint']);
        $this->meta = $meta;
    }

    /* ---- Scopes --------------------------------------------------------- */

    /** Runs that never reached a terminal state (failed, or crashed mid-run). */
    public function scopePartials(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_FAILED]);
    }

    public function scopeSuccessful(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_SUCCESS);
    }
}
