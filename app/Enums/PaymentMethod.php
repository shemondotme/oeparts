<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card         = 'card';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match($this) {
            self::Card         => 'Card',
            self::BankTransfer => 'Bank Transfer',
        };
    }
}
