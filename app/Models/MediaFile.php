<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'uploaded_by', 'file_name', 'file_path',
        'file_url', 'mime_type', 'size', 'alt_text',
        'caption', 'type',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }

    /**
     * file_url is stored as a full absolute URL at upload time
     * (MediaPickerController: Storage::url($path)), which bakes in whatever
     * host/scheme was live THEN. It never gets recomputed, so it silently
     * goes stale on every environment move — confirmed live: this app's
     * media rows still carried http://oeparts.test/... (a pre-Docker
     * hostname) after moving to http://localhost, breaking every
     * manufacturer logo <img src>. Overriding the accessor to always
     * recompute from file_path (which IS portable — just a relative disk
     * path) fixes every existing row immediately, with no data migration,
     * and makes the next environment/domain move (production) immune to
     * this same bug. The stored file_url column is now write-only legacy
     * data; a later migration can drop it (CLAUDE.md Expand/Migrate/Contract,
     * rule #43) once nothing else reads it directly.
     */
    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }
}
