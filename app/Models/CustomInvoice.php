<?php

namespace App\Models;

use App\Enums\CustomInvoiceStatus;
use App\Enums\InvoicePaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A hand-written invoice for a client outside the storefront checkout.
 * Totals are always derived from `items` in the saving hook (bcmath, 2dp) —
 * client-submitted totals are never trusted.
 */
class CustomInvoice extends Model
{
    protected $fillable = [
        'invoice_number', 'status',
        'client_name', 'client_company', 'client_vat_number', 'client_email', 'client_phone',
        'client_address_line1', 'client_address_line2', 'client_city', 'client_postal_code', 'client_country_code',
        'currency', 'issue_date', 'due_date',
        'items', 'discount_amount', 'vat_rate', 'reverse_charge',
        'notes', 'sent_at', 'paid_at', 'created_by',
        'payment_method', 'bank_account_id', 'payment_instructions', 'payment_link_url',
    ];

    /**
     * New instances behave like the database default, so code that builds an
     * invoice without choosing a payment method (and never reloads it) still
     * prints the bank-transfer block, as every invoice did before this existed.
     */
    protected $attributes = [
        'payment_method' => 'bank_transfer',
    ];

    protected $casts = [
        'status' => CustomInvoiceStatus::class,
        'payment_method' => InvoicePaymentMethod::class,
        'issue_date' => 'date',
        'due_date' => 'date',
        'items' => 'array',
        'discount_amount' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'reverse_charge' => 'boolean',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (CustomInvoice $invoice): void {
            $invoice->recalculateTotals();
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(InvoiceBankAccount::class, 'bank_account_id');
    }

    public function isEditable(): bool
    {
        return $this->status === CustomInvoiceStatus::Draft;
    }

    /**
     * @return list<array{description: string, quantity: string, unit_price: string, line_total: string}>
     */
    public function normalizedItems(): array
    {
        $rows = [];

        foreach ((array) $this->items as $item) {
            $quantity = $this->decimal($item['quantity'] ?? '0');
            $unit = $this->decimal($item['unit_price'] ?? '0');

            $rows[] = [
                'description' => (string) ($item['description'] ?? ''),
                'quantity' => $quantity,
                'unit_price' => $unit,
                'line_total' => bcmul($quantity, $unit, 2),
            ];
        }

        return $rows;
    }

    public function recalculateTotals(): void
    {
        $subtotal = '0.00';

        foreach ($this->normalizedItems() as $row) {
            $subtotal = bcadd($subtotal, $row['line_total'], 2);
        }

        // A discount can never exceed the subtotal (would give a negative invoice).
        $discount = $this->decimal($this->discount_amount ?? '0');
        if (bccomp($discount, $subtotal, 2) > 0) {
            $discount = $subtotal;
        }

        $taxable = bcsub($subtotal, $discount, 2);
        $rate = $this->reverse_charge ? '0.00' : $this->decimal($this->vat_rate ?? '0');
        $vat = bcdiv(bcmul($taxable, $rate, 4), '100', 2);

        $this->attributes['discount_amount'] = $discount;
        $this->attributes['subtotal'] = $subtotal;
        $this->attributes['vat_amount'] = $vat;
        $this->attributes['total'] = bcadd($taxable, $vat, 2);
    }

    private function decimal(mixed $value): string
    {
        $value = trim((string) $value);

        return is_numeric($value) && bccomp($value, '0', 6) >= 0
            ? number_format((float) $value, 2, '.', '')
            : '0.00';
    }
}
