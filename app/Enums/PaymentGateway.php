<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case Airwallex    = 'airwallex';
    case Paysera      = 'paysera';
    case BankTransfer = 'bank_transfer';
}
