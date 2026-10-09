<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How VAT is handled on a hand-written invoice. Anything but Standard means 0%
 * on every line, plus the legal wording the PDF prints for that case.
 *
 * The notices are the standard wording for each case under the EU VAT Directive
 * (2006/112/EC); confirm them with your accountant for your own situation.
 */
enum InvoiceVatTreatment: string implements HasLabel
{
    case Standard = 'standard';
    case ReverseCharge = 'reverse_charge';
    case IntraEu = 'intra_eu';
    case Export = 'export';
    case Exempt = 'exempt';

    public function getLabel(): string
    {
        return match ($this) {
            self::Standard => 'Standard VAT',
            self::ReverseCharge => 'EU reverse charge (buyer accounts for VAT)',
            self::IntraEu => 'Intra-EU supply of goods to a VAT-registered buyer',
            self::Export => 'Export outside the EU (0%)',
            self::Exempt => 'VAT exempt (other reason)',
        };
    }

    /** The notice printed on the invoice; null when VAT is charged normally. */
    public function defaultNotice(): ?string
    {
        return match ($this) {
            self::Standard => null,
            self::ReverseCharge => 'Reverse charge — VAT to be accounted for by the recipient under Article 194/196 of Council Directive 2006/112/EC.',
            self::IntraEu => 'Intra-Community supply of goods — exempt from VAT under Article 138 of Council Directive 2006/112/EC.',
            self::Export => 'Export of goods to a destination outside the European Union — exempt from VAT under Article 146 of Council Directive 2006/112/EC.',
            self::Exempt => 'VAT exempt.',
        };
    }
}
