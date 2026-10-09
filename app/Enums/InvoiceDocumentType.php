<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What a hand-written document is. Decides its number series, its PDF title and
 * wording, and whether it asks the client to pay.
 */
enum InvoiceDocumentType: string implements HasColor, HasLabel
{
    case Quote = 'quote';
    case Proforma = 'proforma';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    public function getLabel(): string
    {
        return match ($this) {
            self::Quote => 'Quotation',
            self::Proforma => 'Proforma invoice',
            self::Invoice => 'Invoice',
            self::CreditNote => 'Credit note',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Quote => 'info',
            self::Proforma => 'warning',
            self::Invoice => 'primary',
            self::CreditNote => 'danger',
        };
    }

    /** Heading printed on the PDF. */
    public function pdfTitle(): string
    {
        return match ($this) {
            self::Quote => 'QUOTATION',
            self::Proforma => 'PROFORMA INVOICE',
            self::Invoice => 'INVOICE',
            self::CreditNote => 'CREDIT NOTE',
        };
    }

    public function sequenceType(): SequenceType
    {
        return match ($this) {
            self::Quote => SequenceType::Quote,
            self::Proforma => SequenceType::Proforma,
            self::Invoice => SequenceType::Invoice,
            self::CreditNote => SequenceType::CreditNote,
        };
    }

    /** Label of the date column ("Due", "Valid until", …). */
    public function dueLabel(): string
    {
        return match ($this) {
            self::Quote => 'Valid until',
            self::Proforma => 'Pay before',
            self::Invoice => 'Due',
            self::CreditNote => 'Date',
        };
    }

    /** Does this document ask the client to pay (so it carries the payment block)? */
    public function requestsPayment(): bool
    {
        return $this === self::Proforma || $this === self::Invoice;
    }

    /** Credit notes show their amounts as negatives. */
    public function isCredit(): bool
    {
        return $this === self::CreditNote;
    }

    /** A line printed under the totals that stops the document being mistaken for a tax invoice. */
    public function disclaimer(): ?string
    {
        return match ($this) {
            self::Quote => 'This is a quotation, not an invoice. Prices are valid until the date shown.',
            self::Proforma => 'This is a proforma invoice, not a tax invoice. A tax invoice will be issued when payment is received.',
            default => null,
        };
    }
}
