<?php

namespace App\Enums;

enum SequenceType: string
{
    case Order = 'order';
    case Invoice = 'invoice';
    case Rma = 'rma';
    case Quote = 'quote';
    case Proforma = 'proforma';
    case CreditNote = 'credit_note';
}
