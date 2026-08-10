<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_id', 'session_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (AdminSession $session) {
            $session->created_at ??= now();
        });
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
