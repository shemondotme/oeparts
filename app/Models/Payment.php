<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'gateway', 'transaction_id',
        'status', 'amount', 'gateway_response',
    ];

    protected $casts = [
        'gateway' => PaymentGateway::class,
        'status' => PaymentTransactionStatus::class,
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
