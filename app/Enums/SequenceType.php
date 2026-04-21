<?php

namespace App\Enums;

enum SequenceType: string
{
    case Order   = 'order';
    case Invoice = 'invoice';
    case Rma     = 'rma';
}
