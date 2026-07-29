<?php

namespace App\Enums;

enum BulkUpdateAction: string
{
    case PriceIncrease = 'price_increase';
    case PriceDecrease = 'price_decrease';
    case PriceSet = 'price_set';
    case ConditionSet = 'condition_set';
    case MarkActive = 'mark_active';
    case MarkInactive = 'mark_inactive';
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';
    case DeliveryTimeSet = 'delivery_time_set';
    case MoqSet = 'moq_set';
    case Import = 'import';
    case Revert = 'revert';
}
