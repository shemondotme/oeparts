<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BackupChunk (Module 14/21, Chunk 2.1) — an individual chunked piece of a
 * backup run: a DB table chunk, a file volume, or the .env snapshot. Splitting
 * a run into parts is what makes the engine resumable and enables partial
 * restore (DB-only / files-only / single-table) later on.
 *
 * Renamed from BackupPart — "Part" collided with the storefront's car-parts
 * domain (Product, PartInquiry). Table stays `backup_parts` (set explicitly
 * below, since Laravel would otherwise infer `backup_chunks` from the new
 * class name) — no migration/data change, this is a class-identifier-only
 * rename.
 *
 * @property array|null $meta
 */
class BackupChunk extends Model
{
    use HasFactory;

    protected $table = 'backup_parts';

    public const TYPE_DB    = 'db';
    public const TYPE_FILES = 'files';
    public const TYPE_ENV   = 'env';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'backup_run_id', 'type', 'sequence', 'name', 'disk',
        'path', 'sha256', 'bytes', 'rows', 'meta',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'bytes'    => 'integer',
        'rows'     => 'integer',
        'meta'     => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class, 'backup_run_id');
    }
}
