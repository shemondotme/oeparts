<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'revisionable_type', 'revisionable_id', 'content_snapshot', 'admin_id',
    ];

    protected $casts = [
        'content_snapshot' => 'array',
    ];

    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
