<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment received against a custom invoice or proforma.
 */
class CustomInvoicePayment extends Model
{
    protected $fillable = ['custom_invoice_id', 'amount', 'paid_on', 'method', 'reference', 'note', 'created_by'];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_on' => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomInvoice::class, 'custom_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
