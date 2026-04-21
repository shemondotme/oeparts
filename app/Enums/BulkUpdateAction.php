<?php

namespace App\Enums;

enum BulkUpdateAction: string
{
    case PriceIncrease = 'price_increase';
    case PriceDecrease = 'price_decrease';
    case StockIn       = 'stock_in';
    case StockOut      = 'stock_out';
    case Import        = 'import';
}
