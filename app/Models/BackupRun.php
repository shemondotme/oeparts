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
    public const PROFILE_UPDATE_SAFETY = 'update_safety';  // slim, taken just before an update

    public const PROFILE_FULL = 'full';           // full disaster-recovery backup (db + files)

    public const PROFILE_DATABASE_ONLY = 'database_only';  // db only, admin-triggered

    public const PROFILE_FILES_ONLY = 'files_only';     // files only, admin-triggered

    /**
     * A site-migration export (.oeparts). It reuses this table and the engine's resumable state machine, but it is
     * NOT a backup: no scope that answers "is there a good backup?" may see it (see the scopes below), it is never
     * pruned by the retention plan, and it cannot be restored.
     */
    public const PROFILE_EXPORT = 'export';

    /** Statuses. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    /** Triggers. */
    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_SCHEDULED = 'scheduled';

    public const TRIGGER_PRE_UPDATE = 'pre_update';

    protected $fillable = [
        'profile', 'status', 'trigger', 'disk', 'encrypted',
        'app_version', 'php_version', 'db_version',
        'total_bytes', 'part_count', 'manifest_path', 'checksum',
        'started_at', 'finished_at', 'expires_at', 'error', 'meta',
    ];

    protected $casts = [
        'encrypted' => 'boolean',
        'total_bytes' => 'integer',
        'part_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'expires_at' => 'datetime',
        'meta' => 'array',
    ];

    public function parts(): HasMany
    {
        return $this->hasMany(BackupChunk::class);
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

    /** A stable owner token for the shared update/backup lock. An export owns it under its own prefix. */
    public function lockOwner(): string
    {
        return ($this->profile === self::PROFILE_EXPORT ? 'export:' : 'backup:').$this->getKey();
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

    /**
     * Backups that never reached a terminal state (failed, or crashed mid-run). Exports are NOT included: an export
     * idle for an hour is not an abandoned backup to reap, and the janitor that reaps those would delete its staging.
     */
    public function scopePartials(Builder $q): Builder
    {
        return $q->where('profile', '<>', self::PROFILE_EXPORT)->whereIn('status', [self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_FAILED]);
    }

    /**
     * Successful BACKUPS. A finished export also ends in status `success`, and every consumer that asks "is there a
     * good backup?" (the health check, retention, the cleanup plan) would believe it, so exports are excluded here, in
     * the scope, rather than at each caller.
     */
    public function scopeSuccessful(Builder $q): Builder
    {
        return $q->where('profile', '<>', self::PROFILE_EXPORT)->where('status', self::STATUS_SUCCESS);
    }

    /** Only site-migration exports. */
    public function scopeExports(Builder $q): Builder
    {
        return $q->where('profile', self::PROFILE_EXPORT);
    }

    /** Everything except site-migration exports: what the backup screens and janitors work with. */
    public function scopeNotExports(Builder $q): Builder
    {
        return $q->where('profile', '<>', self::PROFILE_EXPORT);
    }

    /** For the few callers that really mean "any finished run, export or not". */
    public function scopeSuccessfulIncludingExports(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_SUCCESS);
    }

    /** For the few callers that really mean "any unfinished or failed run, export or not". */
    public function scopePartialsIncludingExports(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_FAILED]);
    }

    /**
     * Is a run still making progress (touched within $freshSeconds)? Such a run
     * legitimately owns the shared backup/update lock however old the lock file is,
     * so a janitor or watchdog must not release it. ONE helper for both
     * BackupJanitor and UpdateWatchdog, so they cannot disagree again (the watchdog
     * once had no such guard and freed the lock of a backup that simply took longer
     * than its own stale threshold).
     */
    public static function hasFreshActiveRun(int $freshSeconds): bool
    {
        // Backups only: an export is kept alive by its own custody rule (BackupLock::heldByLiveExport) with its own horizon.
        return static::query()
            ->notExports()
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_RUNNING])
            ->where('updated_at', '>=', now()->subSeconds($freshSeconds))
            ->exists();
    }

    /**
     * A successful, un-pruned run — the one predicate every restore path
     * (files-only in BackupDashboard, full files+DB in ProductionRestoreService)
     * must share so they never disagree on what's safe to restore from.
     */
    public function isRestorable(): bool
    {
        // A sealed export also ends `success`, but it has no manifest to restore from and must never be offered as a backup.
        return $this->profile !== self::PROFILE_EXPORT
            && $this->status === self::STATUS_SUCCESS
            && ! ($this->meta['pruned_at'] ?? null);
    }
}
