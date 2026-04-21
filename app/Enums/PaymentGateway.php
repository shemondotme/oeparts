<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case Airwallex    = 'airwallex';
    case BankTransfer = 'bank_transfer';
}
