<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'admin_id', 'action', 'model_type', 'model_id',
        'old_values', 'new_values', 'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * created_at is deliberately not mass-assignable (callers shouldn't be
     * backdating activity), and $timestamps = false means Eloquent won't
     * auto-populate it either — without this, every ActivityLog::create()
     * call across the app silently persists a NULL created_at.
     */
    protected static function booted(): void
    {
        static::creating(function (ActivityLog $log) {
            $log->created_at ??= now();
        });
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('model');
    }
}
