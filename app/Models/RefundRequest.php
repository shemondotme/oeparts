<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundRequest extends Model
{
    protected $fillable = [
        'order_id', 'user_id', 'reason', 'return_images',
        'amount_requested', 'status', 'admin_note', 'processed_at',
    ];

    protected $casts = [
        'return_images'    => 'array',
        'amount_requested' => 'decimal:2',
        'status'           => RefundStatus::class,
        'processed_at'     => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
