<?php

namespace App\Enums;

enum EmailTemplate: string
{
    case OrderConfirmation  = 'order_confirmation';
    case OrderStatus        = 'order_status';
    case OrderShipped       = 'order_shipped';
    case Welcome            = 'welcome';
    case Otp                = 'otp';
    case RefundProcessed    = 'refund_processed';
    case AbandonedCart      = 'abandoned_cart';
    case NewsletterConfirm  = 'newsletter_confirm';
    case PasswordReset      = 'password_reset';
    case ContactReply       = 'contact_reply';
}
