<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
