<?php

namespace App\Models;

use App\Enums\CustomInvoiceStatus;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoicePaymentMethod;
use App\Enums\InvoiceVatTreatment;
use App\Services\InvoiceCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A hand-written invoice for a client outside the storefront checkout.
 * Totals are always derived from `items` in the saving hook (bcmath, 2dp) —
 * client-submitted totals are never trusted. See InvoiceCalculator for the rules.
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
        'vat_treatment', 'vat_exemption_note', 'supply_date', 'discount_type', 'discount_percent',
        'po_number', 'delivery_terms', 'terms_text', 'internal_notes',
        'document_type', 'parent_id',
    ];

    /**
     * New instances behave like the database defaults, so code that builds an
     * invoice without choosing these (and never reloads it) still gets the
     * bank-transfer block and standard VAT, as every invoice did before they existed.
     */
    protected $attributes = [
        'payment_method' => 'bank_transfer',
        'vat_treatment' => 'standard',
        'discount_type' => 'amount',
        'document_type' => 'invoice',
    ];

    protected $casts = [
        'status' => CustomInvoiceStatus::class,
        'payment_method' => InvoicePaymentMethod::class,
        'vat_treatment' => InvoiceVatTreatment::class,
        'document_type' => InvoiceDocumentType::class,
        'issue_date' => 'date',
        'due_date' => 'date',
        'supply_date' => 'date',
        'items' => 'array',
        'vat_breakdown' => 'array',
        'discount_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
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

    /** The document this one was created from (quote -> proforma -> invoice, invoice -> credit note). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Documents created from this one. */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
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
     * The VAT treatment in force. The old reverse-charge on/off flag is still honoured
     * for anything that sets only it (older code, imports): while the treatment is
     * left at 'standard', a set flag means reverse charge.
     */
    public function effectiveTreatment(): InvoiceVatTreatment
    {
        // The cast hands back the enum; an unset/unknown stored value falls back to standard.
        $treatment = $this->vat_treatment ?? InvoiceVatTreatment::Standard;

        if ($treatment === InvoiceVatTreatment::Standard && $this->reverse_charge) {
            return InvoiceVatTreatment::ReverseCharge;
        }

        return $treatment;
    }

    /** The legal notice for a non-standard treatment (the admin's own wording wins), else null. */
    public function vatNotice(): ?string
    {
        $treatment = $this->effectiveTreatment();

        if ($treatment === InvoiceVatTreatment::Standard) {
            return null;
        }

        return filled($this->vat_exemption_note) ? (string) $this->vat_exemption_note : $treatment->defaultNotice();
    }

    /**
     * Per-rate VAT rows: as stored at the last save, or computed on the fly for an invoice
     * saved before the breakdown was recorded.
     *
     * @return list<array{rate: string, base: string, vat: string}>
     */
    public function breakdownRows(): array
    {
        $stored = $this->vat_breakdown;

        return is_array($stored) && $stored !== [] ? $stored : $this->calculation()['breakdown'];
    }

    /**
     * @return list<array{description: string, part_number: string, unit: string, quantity: string, unit_price: string, discount_percent: string, vat_rate: string, line_total: string, product_id: ?int}>
     */
    public function normalizedItems(): array
    {
        return $this->calculation()['lines'];
    }

    public function recalculateTotals(): void
    {
        $result = $this->calculation();
        $treatment = $this->effectiveTreatment();

        $this->attributes['vat_treatment'] = $treatment->value;
        $this->attributes['reverse_charge'] = $treatment === InvoiceVatTreatment::ReverseCharge ? 1 : 0;
        $this->attributes['discount_amount'] = $result['discount_amount'];
        $this->attributes['subtotal'] = $result['subtotal'];
        $this->attributes['vat_amount'] = $result['vat_amount'];
        $this->attributes['total'] = $result['total'];
        $this->attributes['vat_breakdown'] = json_encode($result['breakdown']);
    }

    /** @return array<string, mixed> */
    private function calculation(): array
    {
        $type = (string) ($this->discount_type ?: 'amount');

        return app(InvoiceCalculator::class)->calculate(
            (array) $this->items,
            $this->effectiveTreatment()->value,
            $this->vat_rate ?? 0,
            $type,
            $type === 'percent' ? ($this->discount_percent ?? 0) : ($this->discount_amount ?? 0),
        );
    }
}
