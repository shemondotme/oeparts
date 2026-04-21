<?php

namespace App\Enums;

enum ContactSubjectType: string
{
    case GeneralInquiry    = 'general_inquiry';
    case PartNotFound      = 'part_not_found';
    case OrderIssue        = 'order_issue';
    case ShippingQuestion  = 'shipping_question';
    case ReturnRefund      = 'return_refund';
    case B2bPartnership    = 'b2b_partnership';
    case Other             = 'other';
}
