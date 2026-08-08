<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Card         = 'card';
    case Paysera      = 'paysera';
    case BankTransfer = 'bank_transfer';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match($this) {
            self::Card         => 'Card',
            self::Paysera      => 'Paysera',
            self::BankTransfer => 'Bank Transfer',
        };
    }
}
