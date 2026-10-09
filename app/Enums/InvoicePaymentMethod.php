<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a client is asked to pay a custom invoice. Decides which "how to pay"
 * block the PDF and the email carry.
 */
enum InvoicePaymentMethod: string implements HasLabel
{
    case BankTransfer = 'bank_transfer';
    case PaymentLink = 'payment_link';
    case Cash = 'cash';
    case Other = 'other';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank transfer',
            self::PaymentLink => 'Online payment link',
            self::Cash => 'Cash on delivery / pickup',
            self::Other => 'Other (write your own instructions)',
            self::None => 'No payment block (e.g. already paid)',
        };
    }
}
