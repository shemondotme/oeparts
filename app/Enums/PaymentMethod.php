<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Card         = 'card';
    case BankTransfer = 'bank_transfer';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match($this) {
            self::Card         => 'Card',
            self::BankTransfer => 'Bank Transfer',
        };
    }
}
