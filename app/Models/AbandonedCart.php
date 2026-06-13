<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbandonedCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'guest_email', 'cart_snapshot',
        'last_active_at', 'recovery_email_sent',
    ];

    protected $casts = [
        'cart_snapshot'       => 'array',
        'last_active_at'      => 'datetime',
        'recovery_email_sent' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
