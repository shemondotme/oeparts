<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProductImportRun — one row per bulk CSV import run.
 *
 * Drives the chunked, resumable Import Engine FSM (mirrors BackupRun).
 * `status` is the coarse lifecycle (running → success|failed); the
 * fine-grained, resumable checkpoint (which stage, which byte offset) lives
 * in `meta['checkpoint']` so a browser-closed / crashed run can pick up
 * exactly where it stopped. `meta['headers']`/`meta['data_start_offset']`
 * are run-level (not stage-checkpoint-local) since ImportRowsStage needs
 * what ValidateHeaderStage discovered, after that stage's own checkpoint
 * state has already been reset.
 *
 * @property array|null $meta
 * @property array|null $errors
 */
class ProductImportRun extends Model
{
    use HasFactory;

    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    /** Row-level errors beyond this count still increment error_count but aren't individually stored. */
    public const MAX_STORED_ERRORS = 500;

    protected $fillable = [
        'admin_id', 'status', 'original_filename', 'disk', 'path', 'update_existing',
        'total_rows', 'processed_rows', 'created_count', 'updated_count', 'skipped_count',
        'error_count', 'errors', 'started_at', 'finished_at', 'error', 'meta',
    ];

    protected $casts = [
        'update_existing' => 'boolean',
        'total_rows'       => 'integer',
        'processed_rows'   => 'integer',
        'created_count'    => 'integer',
        'updated_count'    => 'integer',
        'skipped_count'    => 'integer',
        'error_count'      => 'integer',
        'errors'           => 'array',
        'started_at'       => 'datetime',
        'finished_at'      => 'datetime',
        'meta'             => 'array',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
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

    /* ---- Run-level scratch data shared across stages --------------------- */

    public function getMetaValue(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    public function setMetaValue(string $key, mixed $value): void
    {
        $meta = $this->meta ?? [];
        $meta[$key] = $value;
        $this->meta = $meta;
    }

    /* ---- Error accumulation (capped, but always fully counted) ---------- */

    public function addError(int $rowNumber, string $message): void
    {
        $this->error_count++;

        $errors = $this->errors ?? [];
        if (count($errors) < self::MAX_STORED_ERRORS) {
            $errors[]     = ['row' => $rowNumber, 'message' => $message];
            $this->errors = $errors;
        }
    }
}
