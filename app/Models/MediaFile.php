<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaFile extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uploaded_by', 'file_name', 'file_path',
        'file_url', 'mime_type', 'size', 'alt_text',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }
}
