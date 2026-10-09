<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client invoices are written to, saved once and picked on the invoice form.
 */
class InvoiceClient extends Model
{
    protected $fillable = [
        'name', 'company', 'vat_number', 'email', 'phone',
        'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country_code',
        'currency', 'language', 'notes', 'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(CustomInvoice::class, 'client_id');
    }

    /** "Company — Contact" for pickers and lists. */
    public function displayName(): string
    {
        return filled($this->company) ? $this->company.' — '.$this->name : (string) $this->name;
    }

    /**
     * This client as the client_* fields of an invoice.
     *
     * @return array<string, mixed>
     */
    public function toInvoiceFields(): array
    {
        return [
            'client_id' => $this->id,
            'client_name' => $this->name,
            'client_company' => $this->company,
            'client_vat_number' => $this->vat_number,
            'client_email' => $this->email,
            'client_phone' => $this->phone,
            'client_address_line1' => $this->address_line1,
            'client_address_line2' => $this->address_line2,
            'client_city' => $this->city,
            'client_state' => $this->state,
            'client_postal_code' => $this->postal_code,
            'client_country_code' => $this->country_code,
            'language' => $this->language ?: 'en',
        ];
    }

    /**
     * A client built from the client_* fields of an invoice form.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromInvoiceFields(array $data): self
    {
        return new self([
            'name' => $data['client_name'] ?? '',
            'company' => $data['client_company'] ?? null,
            'vat_number' => $data['client_vat_number'] ?? null,
            'email' => $data['client_email'] ?? null,
            'phone' => $data['client_phone'] ?? null,
            'address_line1' => $data['client_address_line1'] ?? '',
            'address_line2' => $data['client_address_line2'] ?? null,
            'city' => $data['client_city'] ?? '',
            'state' => $data['client_state'] ?? null,
            'postal_code' => $data['client_postal_code'] ?? null,
            'country_code' => $data['client_country_code'] ?? '',
            'currency' => $data['currency'] ?? null,
            'language' => $data['language'] ?? null,
        ]);
    }
}
