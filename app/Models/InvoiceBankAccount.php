<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A bank account that can be printed on invoices as "how to pay". The single
 * account in Settings → Store Operations stays the default; these are additional
 * ones (typically one per invoicing currency, or an international account with
 * SWIFT/BIC and an intermediary bank).
 */
class InvoiceBankAccount extends Model
{
    protected $fillable = [
        'label', 'currency', 'account_holder', 'bank_name', 'iban', 'bic',
        'intermediary_bank', 'instructions', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** IBAN as typed by people ("lt12 3456 ...") → canonical upper-case, no spaces. */
    public function setIbanAttribute(?string $value): void
    {
        $this->attributes['iban'] = strtoupper(preg_replace('/\s+/', '', (string) $value) ?? '');
    }

    public function setBicAttribute(?string $value): void
    {
        $value = strtoupper(preg_replace('/\s+/', '', (string) $value) ?? '');
        $this->attributes['bic'] = $value === '' ? null : $value;
    }

    /** IBAN in the printed form: groups of four. */
    public function formattedIban(): string
    {
        return trim(chunk_split((string) $this->iban, 4, ' '));
    }

    /**
     * Mod-97 check (ISO 13616). Catches typos in the one field where a typo sends
     * a client's money to a stranger.
     */
    public static function isValidIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');

        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder === 1;
    }
}
